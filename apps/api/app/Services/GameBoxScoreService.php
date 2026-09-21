<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Enums\RegistrationStatus;
use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\PlayerRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GameBoxScoreService
{
    private const STAT_COLUMNS = ['points', 'rebounds', 'assists', 'steals', 'blocks', 'turnovers', 'fouls'];

    public function __construct(private readonly GameSchedulingService $scheduling) {}

    public function updateLineup(Game $game, array $attributes, User $user): Game
    {
        $updated = DB::transaction(function () use ($game, $attributes, $user): Game {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            if (! in_array($locked->status, [GameStatus::Scheduled, GameStatus::Delayed, GameStatus::Live, GameStatus::Suspended], true)) {
                throw ValidationException::withMessages(['lineup' => 'The lineup cannot be changed after the game is finalized or removed from the schedule.']);
            }

            $homeIds = collect($attributes['home_player_registration_ids'])->map(fn ($id) => (int) $id)->values();
            $awayIds = collect($attributes['away_player_registration_ids'])->map(fn ($id) => (int) $id)->values();
            $this->validateRoster($locked->home_team_registration_id, $homeIds->all(), 'home_player_registration_ids');
            $this->validateRoster($locked->away_team_registration_id, $awayIds->all(), 'away_player_registration_ids');
            $this->validateStarters($attributes, 'home', $homeIds->all());
            $this->validateStarters($attributes, 'away', $awayIds->all());

            $selectedIds = $homeIds->merge($awayIds);
            $removed = $locked->playerStats()->whereNotIn('player_registration_id', $selectedIds)->get();
            if ($removed->contains(fn (GamePlayerStat $row) => collect(self::STAT_COLUMNS)->contains(fn (string $stat) => $row->{$stat} > 0))) {
                throw ValidationException::withMessages(['lineup' => 'A player with recorded statistics cannot be removed from the game lineup.']);
            }
            $locked->playerStats()->whereNotIn('player_registration_id', $selectedIds)->delete();

            foreach ([[$homeIds, $locked->home_team_registration_id], [$awayIds, $locked->away_team_registration_id]] as [$ids, $teamId]) {
                foreach ($ids as $playerRegistrationId) {
                    $locked->playerStats()->firstOrCreate(
                        ['player_registration_id' => $playerRegistrationId],
                        ['team_registration_id' => $teamId],
                    );
                }
            }

            foreach ([['home', $locked->home_team_registration_id], ['away', $locked->away_team_registration_id]] as [$side, $teamId]) {
                $field = $side.'_starter_player_registration_ids';
                if (! array_key_exists($field, $attributes)) {
                    continue;
                }

                $locked->playerStats()->where('team_registration_id', $teamId)->update([
                    'is_starter' => false,
                    'is_on_court' => false,
                    'court_slot' => null,
                ]);
                foreach ($attributes[$field] as $index => $playerRegistrationId) {
                    $locked->playerStats()->where('team_registration_id', $teamId)
                        ->where('player_registration_id', $playerRegistrationId)
                        ->update(['is_starter' => true, 'is_on_court' => true, 'court_slot' => $index + 1]);
                }
            }

            $locked->updated_by = $user->id;
            $locked->save();

            return $locked;
        });

        return $this->scheduling->loadGame($updated);
    }

    public function substitute(Game $game, array $attributes, User $user): Game
    {
        $updated = DB::transaction(function () use ($game, $attributes, $user): Game {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);
            if (! in_array($lockedGame->status, [GameStatus::Live, GameStatus::Suspended], true)) {
                throw ValidationException::withMessages(['team' => 'Substitutions are available only while a game is live or suspended.']);
            }

            $teamId = $attributes['team'] === 'home'
                ? $lockedGame->home_team_registration_id
                : $lockedGame->away_team_registration_id;
            $rows = GamePlayerStat::query()->where('game_id', $lockedGame->id)
                ->where('team_registration_id', $teamId)->lockForUpdate()->get();
            $playerOut = $rows->firstWhere('player_registration_id', (int) $attributes['player_out_registration_id']);
            $playerIn = $rows->firstWhere('player_registration_id', (int) $attributes['player_in_registration_id']);

            if (! $playerOut?->is_on_court) {
                throw ValidationException::withMessages(['player_out_registration_id' => 'Choose a player who is currently on the court.']);
            }
            if ($playerIn === null || $playerIn->is_on_court) {
                throw ValidationException::withMessages(['player_in_registration_id' => 'Choose a bench player from the same game lineup.']);
            }

            $slot = $playerOut->court_slot;
            $playerOut->update(['is_on_court' => false, 'court_slot' => null]);
            $playerIn->update(['is_on_court' => true, 'court_slot' => $slot]);
            $lockedGame->liveEvents()->create([
                'event_type' => 'substitution',
                'team_registration_id' => $teamId,
                'period_number' => $lockedGame->current_period,
                'home_score_after' => $lockedGame->home_score,
                'away_score_after' => $lockedGame->away_score,
                'clock_seconds_remaining' => $lockedGame->effectiveClockSeconds(),
                'details' => [
                    'player_out_registration_id' => $playerOut->player_registration_id,
                    'player_in_registration_id' => $playerIn->player_registration_id,
                    'court_slot' => $slot,
                ],
                'recorded_by' => $user->id,
            ]);
            $lockedGame->update(['updated_by' => $user->id]);

            return $lockedGame;
        });

        return $this->scheduling->loadGame($updated);
    }

    public function updateStat(Game $game, GamePlayerStat $playerStat, array $attributes, User $user): Game
    {
        abort_unless($playerStat->game_id === $game->id, 404);

        $updated = DB::transaction(function () use ($game, $playerStat, $attributes, $user): Game {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);
            if (! in_array($lockedGame->status, [GameStatus::Live, GameStatus::Final], true)) {
                throw ValidationException::withMessages(['stat' => 'Player statistics can only be recorded during a live or final game.']);
            }
            if ($lockedGame->status === GameStatus::Final && blank($attributes['reason'] ?? null)) {
                throw ValidationException::withMessages(['reason' => 'Explain every player-stat change made after finalization.']);
            }

            $lockedStat = GamePlayerStat::query()->lockForUpdate()->findOrFail($playerStat->id);
            $stat = $attributes['stat'];
            $delta = (int) $attributes['delta'];
            $value = (int) $lockedStat->{$stat} + $delta;
            if ($value < 0) {
                throw ValidationException::withMessages(['delta' => ucfirst($stat).' cannot be reduced below zero.']);
            }

            $lockedStat->{$stat} = $value;
            $lockedStat->save();
            $lockedGame->statEvents()->create([
                'game_player_stat_id' => $lockedStat->id,
                'player_registration_id' => $lockedStat->player_registration_id,
                'stat' => $stat,
                'delta' => $delta,
                'value_after' => $value,
                'reason' => $attributes['reason'] ?? null,
                'recorded_by' => $user->id,
            ]);
            $lockedGame->liveEvents()->create([
                'event_type' => 'player_stat',
                'team_registration_id' => $lockedStat->team_registration_id,
                'period_number' => $lockedGame->current_period,
                'home_score_after' => $lockedGame->home_score,
                'away_score_after' => $lockedGame->away_score,
                'clock_seconds_remaining' => $lockedGame->effectiveClockSeconds(),
                'details' => [
                    'player_registration_id' => $lockedStat->player_registration_id,
                    'stat' => $stat,
                    'delta' => $delta,
                    'value_after' => $value,
                    'reason' => $attributes['reason'] ?? null,
                ],
                'recorded_by' => $user->id,
            ]);

            return $lockedGame;
        });

        return $this->scheduling->loadGame($updated);
    }

    public function recordPoints(Game $game, ?int $playerRegistrationId, int $teamRegistrationId, int $delta, User $user, ?string $reason): void
    {
        if ($playerRegistrationId === null) {
            return;
        }

        $stat = GamePlayerStat::query()
            ->where('game_id', $game->id)
            ->where('player_registration_id', $playerRegistrationId)
            ->where('team_registration_id', $teamRegistrationId)
            ->lockForUpdate()
            ->first();
        if ($stat === null) {
            throw ValidationException::withMessages(['player_registration_id' => 'Select a player from this team’s active game lineup.']);
        }

        $value = $stat->points + $delta;
        if ($value < 0) {
            throw ValidationException::withMessages(['points' => 'The correction cannot reduce the player’s points below zero.']);
        }

        $stat->update(['points' => $value]);
        $game->statEvents()->create([
            'game_player_stat_id' => $stat->id,
            'player_registration_id' => $stat->player_registration_id,
            'stat' => 'points',
            'delta' => $delta,
            'value_after' => $value,
            'reason' => $reason,
            'recorded_by' => $user->id,
        ]);
    }

    private function validateRoster(int $teamRegistrationId, array $playerIds, string $field): void
    {
        $validCount = PlayerRegistration::query()
            ->where('season_team_registration_id', $teamRegistrationId)
            ->where('status', RegistrationStatus::Approved)
            ->whereIn('id', $playerIds)
            ->count();

        if ($validCount !== count($playerIds)) {
            throw ValidationException::withMessages([$field => 'Every selected player must be approved on this team’s season roster.']);
        }
    }

    private function validateStarters(array $attributes, string $side, array $lineupIds): void
    {
        $field = $side.'_starter_player_registration_ids';
        if (! array_key_exists($field, $attributes)) {
            return;
        }

        if (array_diff($attributes[$field], $lineupIds) !== []) {
            throw ValidationException::withMessages([$field => 'Every starter must also be selected in this team’s game lineup.']);
        }
    }
}
