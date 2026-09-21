<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Enums\RegistrationStatus;
use App\Enums\StandingQualificationStatus;
use App\Models\Division;
use App\Models\Season;
use App\Models\Standing;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StandingService
{
    public function list(Season $season, ?int $divisionId = null): Collection
    {
        if ($divisionId !== null) {
            $this->division($season, $divisionId);
        }

        return Standing::query()
            ->whereBelongsTo($season)
            ->when($divisionId, fn ($query, int $id) => $query->where('division_id', $id))
            ->with(['division', 'teamRegistration.team', 'updatedBy'])
            ->orderBy('division_id')
            ->orderBy('rank')
            ->orderBy('id')
            ->get();
    }

    public function recalculate(Season $season, int $divisionId, User $user): Collection
    {
        $division = $this->division($season, $divisionId);

        DB::transaction(function () use ($season, $division, $user): void {
            $registrations = $season->teamRegistrations()
                ->where('division_id', $division->id)
                ->where('status', RegistrationStatus::Approved)
                ->with('team:id,name')
                ->get();
            $metadata = Standing::query()
                ->where('division_id', $division->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('team_registration_id');
            $statistics = $registrations->mapWithKeys(fn ($registration): array => [
                $registration->id => [
                    'team_registration_id' => $registration->id,
                    'team_name' => $registration->team->name,
                    'played' => 0,
                    'wins' => 0,
                    'losses' => 0,
                    'points_for' => 0,
                    'points_against' => 0,
                ],
            ]);

            $season->games()
                ->where('division_id', $division->id)
                ->where('status', GameStatus::Final)
                ->get([
                    'home_team_registration_id', 'away_team_registration_id',
                    'home_score', 'away_score',
                ])
                ->each(function ($game) use ($statistics): void {
                    $home = $statistics->get($game->home_team_registration_id);
                    $away = $statistics->get($game->away_team_registration_id);

                    if ($home === null || $away === null) {
                        return;
                    }

                    $home['played']++;
                    $away['played']++;
                    $home['points_for'] += (int) $game->home_score;
                    $home['points_against'] += (int) $game->away_score;
                    $away['points_for'] += (int) $game->away_score;
                    $away['points_against'] += (int) $game->home_score;

                    if ($game->home_score > $game->away_score) {
                        $home['wins']++;
                        $away['losses']++;
                    } else {
                        $away['wins']++;
                        $home['losses']++;
                    }

                    $statistics->put($game->home_team_registration_id, $home);
                    $statistics->put($game->away_team_registration_id, $away);
                });

            $rows = $statistics
                ->sort(function (array $left, array $right): int {
                    $winPercentage = ($right['wins'] * max(1, $left['played']))
                        <=> ($left['wins'] * max(1, $right['played']));
                    $gamesPlayed = $right['played'] <=> $left['played'];
                    $pointDifference = ($right['points_for'] - $right['points_against'])
                        <=> ($left['points_for'] - $left['points_against']);

                    return $winPercentage
                        ?: $gamesPlayed
                        ?: $pointDifference
                        ?: ($right['points_for'] <=> $left['points_for'])
                        ?: strcasecmp($left['team_name'], $right['team_name']);
                })
                ->values()
                ->map(function (array $statistics, int $index) use ($season, $division, $metadata, $user): array {
                    $existing = $metadata->get($statistics['team_registration_id']);

                    return [
                        'season_id' => $season->id,
                        'division_id' => $division->id,
                        'team_registration_id' => $statistics['team_registration_id'],
                        'rank' => $index + 1,
                        'played' => $statistics['played'],
                        'wins' => $statistics['wins'],
                        'losses' => $statistics['losses'],
                        'points_for' => $statistics['points_for'],
                        'points_against' => $statistics['points_against'],
                        'qualification_status' => $existing?->qualification_status->value
                            ?? StandingQualificationStatus::Pending->value,
                        'notes' => $existing?->notes,
                        'updated_by' => $user->id,
                        'created_at' => $existing?->created_at ?? now(),
                        'updated_at' => now(),
                    ];
                });

            Standing::query()->where('division_id', $division->id)->delete();

            if ($rows->isNotEmpty()) {
                Standing::query()->insert($rows->all());
            }
        });

        return $this->list($season, $division->id);
    }

    public function updateMetadata(Season $season, array $attributes, User $user): Collection
    {
        $division = $this->division($season, (int) $attributes['division_id']);

        return DB::transaction(function () use ($season, $division, $attributes, $user): Collection {
            $this->recalculate($season, $division->id, $user);

            collect($attributes['rows'])->each(function (array $row, int $index) use ($season, $division, $user): void {
                $registration = $season->teamRegistrations()
                    ->where('division_id', $division->id)
                    ->where('status', RegistrationStatus::Approved)
                    ->find($row['team_registration_id']);

                if ($registration === null) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.team_registration_id" => 'Select an approved team from this season and division.',
                    ]);
                }

                Standing::query()
                    ->where('division_id', $division->id)
                    ->where('team_registration_id', $registration->id)
                    ->update([
                        'qualification_status' => $row['qualification_status'],
                        'notes' => $row['notes'] ?? null,
                        'updated_by' => $user->id,
                        'updated_at' => now(),
                    ]);
            });

            return $this->list($season, $division->id);
        });
    }

    private function division(Season $season, int $divisionId): Division
    {
        return $season->divisions()->findOrFail($divisionId);
    }
}
