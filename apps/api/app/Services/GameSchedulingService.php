<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Organization;
use App\Models\Season;
use App\Models\SeasonTeamRegistration;
use App\Models\User;
use App\Rules\SupportedLivestreamUrl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GameSchedulingService
{
    public function __construct(
        private readonly CompetitionConfigurationService $configuration,
        private readonly StandingService $standings,
    ) {}

    public function create(Organization $organization, Competition $competition, Season $season, array $attributes, User $user): Game
    {
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        $normalized = $this->normalizedAttributes($organization, $season, $attributes);
        $this->rejectConflictsUnlessAllowed($season, $normalized, (bool) ($attributes['allow_conflicts'] ?? false));

        $game = $season->games()->create([
            ...Arr::except($normalized, ['allow_conflicts']),
            'status' => GameStatus::Scheduled,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return $this->loadGame($game);
    }

    public function update(Organization $organization, Competition $competition, Season $season, Game $game, array $attributes, User $user): Game
    {
        $this->ensureGameBelongsTo($organization, $competition, $season, $game);

        return DB::transaction(function () use ($organization, $season, $game, $attributes, $user): Game {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);
            $original = $lockedGame->getAttributes();
            $candidate = [
                'division_id' => $attributes['division_id'] ?? $lockedGame->division_id,
                'home_team_registration_id' => $attributes['home_team_registration_id'] ?? $lockedGame->home_team_registration_id,
                'away_team_registration_id' => $attributes['away_team_registration_id'] ?? $lockedGame->away_team_registration_id,
                'venue_id' => array_key_exists('venue_id', $attributes) ? $attributes['venue_id'] : $lockedGame->venue_id,
                'scheduled_at' => $attributes['scheduled_at'] ?? $lockedGame->scheduled_at,
                'estimated_duration_minutes' => $attributes['estimated_duration_minutes'] ?? $lockedGame->estimated_duration_minutes,
            ];
            $normalizedCandidate = $this->normalizedAttributes($organization, $season, $candidate);
            $targetStatus = isset($attributes['status']) ? GameStatus::from($attributes['status']) : $lockedGame->status;
            $scheduleChanged = $normalizedCandidate['division_id'] !== $lockedGame->division_id
                || $normalizedCandidate['home_team_registration_id'] !== $lockedGame->home_team_registration_id
                || $normalizedCandidate['away_team_registration_id'] !== $lockedGame->away_team_registration_id
                || $normalizedCandidate['venue_id'] !== $lockedGame->venue_id
                || ! $normalizedCandidate['scheduled_at']->equalTo($lockedGame->scheduled_at)
                || $normalizedCandidate['estimated_duration_minutes'] !== $lockedGame->estimated_duration_minutes;
            $statusEnteredSchedule = isset($attributes['status']) && $targetStatus->occupiesSchedule() && ! $lockedGame->status->occupiesSchedule();
            if ($scheduleChanged || $statusEnteredSchedule) {
                $this->rejectConflictsUnlessAllowed(
                    $season,
                    $normalizedCandidate,
                    (bool) ($attributes['allow_conflicts'] ?? false),
                    $lockedGame,
                );
            }

            $updates = Arr::except($attributes, ['allow_conflicts', 'change_reason']);
            $updates = [...$updates, ...Arr::only($normalizedCandidate, array_keys($updates))];
            if (array_key_exists('livestream_url', $attributes) || array_key_exists('livestream_status', $attributes)) {
                $url = array_key_exists('livestream_url', $attributes)
                    ? (filled($attributes['livestream_url'] ?? null) ? trim((string) $attributes['livestream_url']) : null)
                    : $lockedGame->livestream_url;
                $provider = $url ? SupportedLivestreamUrl::provider($url) : null;
                if ($url && $provider === null) {
                    throw ValidationException::withMessages(['livestream_url' => 'Use a supported YouTube or Facebook video URL.']);
                }

                $updates['livestream_url'] = $url;
                $updates['livestream_provider'] = $provider;
                $updates['livestream_status'] = $url
                    ? ($attributes['livestream_status'] ?? ($lockedGame->livestream_url === $url ? $lockedGame->livestream_status : 'scheduled'))
                    : 'unavailable';
            }
            $homeScore = (int) ($updates['home_score'] ?? $lockedGame->home_score);
            $awayScore = (int) ($updates['away_score'] ?? $lockedGame->away_score);

            if ($targetStatus === GameStatus::Final && $homeScore === $awayScore) {
                throw ValidationException::withMessages(['home_score' => 'A finalized basketball game cannot end in a tie.']);
            }

            $updates['finalized_at'] = $targetStatus === GameStatus::Final
                ? ($lockedGame->finalized_at ?? now())
                : null;
            $updates['updated_by'] = $user->id;
            $lockedGame->update($updates);
            $lockedGame->changes()->create([
                'changed_by' => $user->id,
                'change_type' => $this->changeType($original, $lockedGame->getAttributes()),
                'from_status' => $original['status'],
                'to_status' => $lockedGame->status->value,
                'old_scheduled_at' => $original['scheduled_at'],
                'new_scheduled_at' => $lockedGame->scheduled_at,
                'old_venue_id' => $original['venue_id'],
                'new_venue_id' => $lockedGame->venue_id,
                'old_home_score' => $original['home_score'],
                'old_away_score' => $original['away_score'],
                'new_home_score' => $lockedGame->home_score,
                'new_away_score' => $lockedGame->away_score,
                'reason' => $attributes['change_reason'],
            ]);

            $standingAttributes = [
                'division_id', 'home_team_registration_id', 'away_team_registration_id',
                'status', 'home_score', 'away_score',
            ];
            if (Arr::only($original, $standingAttributes) !== Arr::only($lockedGame->getAttributes(), $standingAttributes)) {
                collect([(int) $original['division_id'], $lockedGame->division_id])
                    ->unique()
                    ->each(fn (int $divisionId) => $this->standings->recalculate($season, $divisionId, $user));
            }

            return $this->loadGame($lockedGame);
        });
    }

    public function ensureGameBelongsTo(Organization $organization, Competition $competition, Season $season, Game $game): void
    {
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        abort_unless($game->season_id === $season->id, 404);
    }

    public function list(Season $season, array $filters): Collection
    {
        $query = $season->games()->with([
            'division', 'venue', 'homeTeamRegistration.team', 'awayTeamRegistration.team',
            'changes.changedBy', 'changes.oldVenue', 'changes.newVenue',
        ]);

        if (isset($filters['date'])) {
            $start = CarbonImmutable::parse($filters['date'], $season->timezone)
                ->startOfDay()
                ->setTimezone(config('app.timezone'));
            $end = $start->addDay();
            $query->where('scheduled_at', '>=', $start)->where('scheduled_at', '<', $end);
        }

        if (isset($filters['team_registration_id'])) {
            $teamRegistrationId = (int) $filters['team_registration_id'];
            $query->where(function ($nested) use ($teamRegistrationId): void {
                $nested->where('home_team_registration_id', $teamRegistrationId)
                    ->orWhere('away_team_registration_id', $teamRegistrationId);
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->reorder()->orderByDesc('scheduled_at')->orderByDesc('id')->get();
    }

    public function loadGame(Game $game): Game
    {
        return $game->load([
            'season', 'division', 'venue', 'homeTeamRegistration.team', 'awayTeamRegistration.team',
            'homeTeamRegistration.playerRegistrations.player', 'awayTeamRegistration.playerRegistrations.player',
            'changes.changedBy', 'changes.oldVenue', 'changes.newVenue',
            'liveEvents.recordedBy',
            'playerStats.playerRegistration.player', 'statEvents.recordedBy',
        ]);
    }

    private function normalizedAttributes(Organization $organization, Season $season, array $attributes): array
    {
        $divisionId = (int) $attributes['division_id'];
        $homeRegistration = $season->teamRegistrations()->with('team')->find($attributes['home_team_registration_id']);
        $awayRegistration = $season->teamRegistrations()->with('team')->find($attributes['away_team_registration_id']);

        if (! $season->divisions()->whereKey($divisionId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['division_id' => 'Select an active division from this season.']);
        }

        $this->validateTeamRegistration($homeRegistration, $divisionId, 'home_team_registration_id');
        $this->validateTeamRegistration($awayRegistration, $divisionId, 'away_team_registration_id');

        if ($homeRegistration?->is($awayRegistration)) {
            throw ValidationException::withMessages(['away_team_registration_id' => 'Choose two different teams.']);
        }

        $venueId = $attributes['venue_id'] ?? null;
        if ($venueId !== null && ! $organization->venues()->whereKey($venueId)->exists()) {
            throw ValidationException::withMessages(['venue_id' => 'Select a venue from this organization.']);
        }

        return [
            ...$attributes,
            'division_id' => $divisionId,
            'home_team_registration_id' => $homeRegistration->id,
            'away_team_registration_id' => $awayRegistration->id,
            'venue_id' => $venueId,
            'scheduled_at' => CarbonImmutable::parse($attributes['scheduled_at'])->setTimezone(config('app.timezone')),
            'estimated_duration_minutes' => (int) $attributes['estimated_duration_minutes'],
        ];
    }

    private function validateTeamRegistration(?SeasonTeamRegistration $registration, int $divisionId, string $field): void
    {
        if ($registration === null || $registration->division_id !== $divisionId || $registration->status->value !== 'approved') {
            throw ValidationException::withMessages([$field => 'Select an approved team from this season and division.']);
        }
    }

    private function rejectConflictsUnlessAllowed(Season $season, array $attributes, bool $allowConflicts, ?Game $except = null): void
    {
        $warnings = $this->conflictWarnings($season, $attributes, $except);

        if ($warnings !== [] && ! $allowConflicts) {
            throw ValidationException::withMessages([
                'scheduled_at' => [...$warnings, 'Review the conflicts, then enable the override only if this schedule is intentional.'],
            ]);
        }
    }

    private function conflictWarnings(Season $season, array $attributes, ?Game $except): array
    {
        $start = CarbonImmutable::parse($attributes['scheduled_at']);
        $end = $start->addMinutes((int) $attributes['estimated_duration_minutes']);
        $teamIds = [(int) $attributes['home_team_registration_id'], (int) $attributes['away_team_registration_id']];
        $games = $season->games()
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->where(function ($query) use ($teamIds, $attributes): void {
                $query->whereIn('home_team_registration_id', $teamIds)
                    ->orWhereIn('away_team_registration_id', $teamIds);

                if (($attributes['venue_id'] ?? null) !== null) {
                    $query->orWhere('venue_id', $attributes['venue_id']);
                }
            })
            ->with(['homeTeamRegistration.team', 'awayTeamRegistration.team', 'venue'])
            ->get()
            ->filter(fn (Game $game): bool => $game->status->occupiesSchedule());

        return $games->flatMap(function (Game $game) use ($start, $end, $teamIds, $attributes): array {
            $existingStart = $game->scheduled_at->toImmutable();
            $existingEnd = $existingStart->addMinutes($game->estimated_duration_minutes);
            if (! $existingStart->lt($end) || ! $existingEnd->gt($start)) {
                return [];
            }

            $warnings = [];
            $gameTeamIds = [$game->home_team_registration_id, $game->away_team_registration_id];
            if (array_intersect($teamIds, $gameTeamIds) !== []) {
                $warnings[] = "A selected team overlaps game #{$game->id} ({$game->homeTeamRegistration->team->name} vs {$game->awayTeamRegistration->team->name}).";
            }

            if (($attributes['venue_id'] ?? null) !== null && $game->venue_id === (int) $attributes['venue_id']) {
                $warnings[] = "{$game->venue->name} is already used by game #{$game->id} during this time.";
            }

            return $warnings;
        })->unique()->values()->all();
    }

    private function changeType(array $before, array $after): string
    {
        if ($before['scheduled_at'] !== $after['scheduled_at'] || $before['venue_id'] !== $after['venue_id']) {
            return 'schedule';
        }

        if ($before['status'] !== $after['status']) {
            return 'status';
        }

        if ($before['home_score'] !== $after['home_score'] || $before['away_score'] !== $after['away_score']) {
            return 'result';
        }

        if ($before['livestream_url'] !== $after['livestream_url'] || $before['livestream_status'] !== $after['livestream_status']) {
            return 'livestream';
        }

        return 'details';
    }
}
