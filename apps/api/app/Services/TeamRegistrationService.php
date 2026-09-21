<?php

namespace App\Services;

use App\Models\Division;
use App\Models\Organization;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Season;
use App\Models\SeasonTeamRegistration;
use App\Models\Team;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamRegistrationService
{
    public function createTeam(Organization $organization, array $attributes): Team
    {
        return $organization->teams()->create([
            ...$attributes,
            'slug' => $this->uniqueTeamSlug($organization, $attributes['name']),
        ]);
    }

    public function registerTeam(Organization $organization, Season $season, array $attributes): SeasonTeamRegistration
    {
        $team = Team::findOrFail($attributes['team_id']);
        $division = Division::findOrFail($attributes['division_id']);
        $this->ensureTeamBelongsTo($organization, $team);
        abort_unless($division->season_id === $season->id, 404);

        if ($season->teamRegistrations()->where('team_id', $team->id)->exists()) {
            throw ValidationException::withMessages(['team_id' => 'This team is already registered in the season.']);
        }

        return $season->teamRegistrations()->create($attributes);
    }

    public function addPlayer(Organization $organization, Season $season, SeasonTeamRegistration $teamRegistration, array $attributes): PlayerRegistration
    {
        $player = Player::findOrFail($attributes['player_id']);
        $this->ensurePlayerBelongsTo($organization, $player);
        $this->ensureTeamRegistrationBelongsTo($season, $teamRegistration);

        return DB::transaction(function () use ($season, $teamRegistration, $attributes): PlayerRegistration {
            $locked = SeasonTeamRegistration::query()->lockForUpdate()->findOrFail($teamRegistration->id);
            if ($locked->playerRegistrations()->count() >= $season->max_roster_size) {
                throw ValidationException::withMessages(['player_id' => "This roster has reached its {$season->max_roster_size}-player limit."]);
            }
            if ($locked->playerRegistrations()->where('player_id', $attributes['player_id'])->exists()) {
                throw ValidationException::withMessages(['player_id' => 'This player is already registered for the team.']);
            }
            if ($locked->playerRegistrations()->where('jersey_number', $attributes['jersey_number'])->exists()) {
                throw ValidationException::withMessages(['jersey_number' => 'This jersey number is already assigned on the roster.']);
            }

            return $locked->playerRegistrations()->create($attributes);
        });
    }

    public function createAndAddPlayer(Organization $organization, Season $season, SeasonTeamRegistration $teamRegistration, array $attributes): PlayerRegistration
    {
        $this->ensureTeamRegistrationBelongsTo($season, $teamRegistration);

        return DB::transaction(function () use ($organization, $season, $teamRegistration, $attributes): PlayerRegistration {
            $locked = SeasonTeamRegistration::query()->lockForUpdate()->findOrFail($teamRegistration->id);
            if ($locked->playerRegistrations()->count() >= $season->max_roster_size) {
                throw ValidationException::withMessages(['first_name' => "This roster has reached its {$season->max_roster_size}-player limit."]);
            }
            if ($locked->playerRegistrations()->where('jersey_number', $attributes['jersey_number'])->exists()) {
                throw ValidationException::withMessages(['jersey_number' => 'This jersey number is already assigned on the roster.']);
            }

            $possibleDuplicate = $organization->players()
                ->where('first_name', $attributes['first_name'])
                ->where('last_name', $attributes['last_name'])
                ->when(
                    $attributes['date_of_birth'] ?? null,
                    fn ($query, $birthDate) => $query->whereDate('date_of_birth', $birthDate),
                    fn ($query) => $query->whereNull('date_of_birth'),
                )
                ->first();
            if ($possibleDuplicate) {
                throw ValidationException::withMessages([
                    'first_name' => 'A matching player already exists. Use "Select existing player" to avoid a duplicate profile.',
                ]);
            }

            $player = $organization->players()->create([
                ...Arr::only($attributes, ['first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'contact_email', 'contact_phone']),
                'is_active' => true,
            ]);

            return $locked->playerRegistrations()->create([
                ...Arr::only($attributes, ['jersey_number', 'position', 'status']),
                'player_id' => $player->id,
            ]);
        });
    }

    public function updatePlayerRegistration(SeasonTeamRegistration $teamRegistration, PlayerRegistration $registration, array $attributes): PlayerRegistration
    {
        $this->ensurePlayerRegistrationBelongsTo($teamRegistration, $registration);
        if (isset($attributes['jersey_number']) && $teamRegistration->playerRegistrations()
            ->where('jersey_number', $attributes['jersey_number'])->whereKeyNot($registration->id)->exists()) {
            throw ValidationException::withMessages(['jersey_number' => 'This jersey number is already assigned on the roster.']);
        }
        $registration->update($attributes);

        return $registration;
    }

    public function removeTeamRegistration(Season $season, SeasonTeamRegistration $registration): void
    {
        $this->ensureTeamRegistrationBelongsTo($season, $registration);

        DB::transaction(function () use ($registration): void {
            $locked = SeasonTeamRegistration::query()->lockForUpdate()->findOrFail($registration->id);

            if ($locked->homeGames()->exists() || $locked->awayGames()->exists()) {
                throw ValidationException::withMessages([
                    'team_registration' => 'This team cannot be removed because it already has scheduled or completed games. Change its registration status to Withdrawn instead.',
                ]);
            }

            $locked->delete();
        });
    }

    public function ensureTeamBelongsTo(Organization $organization, Team $team): void
    {
        abort_unless($team->organization_id === $organization->id, 404);
    }

    public function ensurePlayerBelongsTo(Organization $organization, Player $player): void
    {
        abort_unless($player->organization_id === $organization->id, 404);
    }

    public function ensureTeamRegistrationBelongsTo(Season $season, SeasonTeamRegistration $registration): void
    {
        abort_unless($registration->season_id === $season->id, 404);
    }

    public function ensurePlayerRegistrationBelongsTo(SeasonTeamRegistration $teamRegistration, PlayerRegistration $registration): void
    {
        abort_unless($registration->season_team_registration_id === $teamRegistration->id, 404);
    }

    private function uniqueTeamSlug(Organization $organization, string $name): string
    {
        $base = Str::slug($name) ?: 'team';
        $slug = $base;
        $suffix = 2;
        while ($organization->teams()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
