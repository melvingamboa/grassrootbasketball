<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiveGameService
{
    public function __construct(
        private readonly GameSchedulingService $scheduling,
        private readonly StandingService $standings,
        private readonly GameBoxScoreService $boxScores,
    ) {}

    public function apply(Game $game, array $attributes, User $user): Game
    {
        $updated = DB::transaction(function () use ($game, $attributes, $user): Game {
            $locked = Game::query()->with('season')->lockForUpdate()->findOrFail($game->id);
            $action = $attributes['action'];
            $clockBefore = $locked->effectiveClockSeconds();

            match ($action) {
                'start' => $this->start($locked),
                'score' => $this->score($locked, $attributes, false, $user),
                'correct' => $this->score($locked, $attributes, true, $user),
                'clock_start' => $this->startClock($locked),
                'clock_pause' => $this->pauseClock($locked),
                'clock_adjust' => $this->adjustClock($locked, (int) $attributes['clock_seconds']),
                'next_period' => $this->nextPeriod($locked),
                'finalize' => $this->finalize($locked),
            };

            $locked->updated_by = $user->id;
            $locked->save();
            $locked->liveEvents()->create([
                'event_type' => $this->eventType($action),
                'team_registration_id' => $this->teamRegistrationId($locked, $attributes),
                'period_number' => $locked->current_period,
                'points_delta' => $this->pointsDelta($attributes),
                'home_score_after' => $locked->home_score,
                'away_score_after' => $locked->away_score,
                'clock_seconds_remaining' => $locked->effectiveClockSeconds(),
                'details' => array_filter([
                    'reason' => $attributes['reason'] ?? null,
                    'player_registration_id' => $attributes['player_registration_id'] ?? null,
                    'clock_seconds_before' => $action === 'clock_adjust' ? $clockBefore : null,
                    'clock_seconds_after' => $action === 'clock_adjust' ? (int) $attributes['clock_seconds'] : null,
                ], fn ($value) => $value !== null),
                'recorded_by' => $user->id,
            ]);

            if ($action === 'finalize' || ($action === 'correct' && $locked->status === GameStatus::Final)) {
                $this->standings->recalculate($locked->season, $locked->division_id, $user);
            }

            return $locked;
        });

        return $this->scheduling->loadGame($updated);
    }

    private function start(Game $game): void
    {
        $this->requireStatus($game, [GameStatus::Scheduled, GameStatus::Delayed, GameStatus::Suspended], 'Only a scheduled, delayed, or suspended game can be started.');

        $game->status = GameStatus::Live;
        $game->started_at ??= now();
        if ($game->clock_seconds_remaining === 0) {
            $game->clock_seconds_remaining = $this->periodDuration($game);
        }
    }

    private function score(Game $game, array $attributes, bool $correction, User $user): void
    {
        $allowed = $correction ? [GameStatus::Live, GameStatus::Final] : [GameStatus::Live];
        $this->requireStatus($game, $allowed, $correction ? 'Corrections are only allowed for live or final games.' : 'Start the game before recording points.');

        $field = $attributes['team'] === 'home' ? 'home_score' : 'away_score';
        $delta = (int) $attributes['points'] * ($correction ? -1 : 1);
        if ((int) $game->{$field} + $delta < 0) {
            throw ValidationException::withMessages(['points' => 'The correction cannot reduce a team score below zero.']);
        }
        if ($correction && ! isset($attributes['player_registration_id'])) {
            $attributedPoints = $game->playerStats()
                ->where('team_registration_id', $this->teamRegistrationId($game, $attributes))
                ->sum('points');
            if ((int) $game->{$field} + $delta < $attributedPoints) {
                throw ValidationException::withMessages(['player_registration_id' => 'These points are assigned to players. Correct the specific player instead.']);
            }
        }

        $game->{$field} = (int) $game->{$field} + $delta;
        $this->boxScores->recordPoints(
            $game,
            isset($attributes['player_registration_id']) ? (int) $attributes['player_registration_id'] : null,
            $this->teamRegistrationId($game, $attributes),
            $delta,
            $user,
            $attributes['reason'] ?? null,
        );
    }

    private function startClock(Game $game): void
    {
        $this->requireStatus($game, [GameStatus::Live], 'Start the game before running the clock.');
        if ($game->effectiveClockSeconds() <= 0) {
            throw ValidationException::withMessages(['action' => 'Advance to the next period before restarting the clock.']);
        }
        if ($game->clock_running) {
            throw ValidationException::withMessages(['action' => 'The game clock is already running.']);
        }

        $game->clock_running = true;
        $game->clock_started_at = now();
    }

    private function pauseClock(Game $game): void
    {
        $this->requireStatus($game, [GameStatus::Live], 'Only a live game clock can be paused.');
        if (! $game->clock_running) {
            throw ValidationException::withMessages(['action' => 'The game clock is already paused.']);
        }

        $game->clock_seconds_remaining = $game->effectiveClockSeconds();
        $game->clock_running = false;
        $game->clock_started_at = null;
    }

    private function adjustClock(Game $game, int $seconds): void
    {
        $this->requireStatus($game, [GameStatus::Live, GameStatus::Suspended], 'The clock can only be adjusted during a live or suspended game.');
        if ($seconds > $this->periodDuration($game)) {
            throw ValidationException::withMessages(['clock_seconds' => 'The adjusted clock cannot exceed the duration of the current period.']);
        }

        $game->clock_seconds_remaining = $seconds;
        $game->clock_started_at = $game->clock_running ? now() : null;
    }

    private function nextPeriod(Game $game): void
    {
        $this->requireStatus($game, [GameStatus::Live], 'Only a live game can advance to the next period.');
        if ($game->clock_running) {
            $this->pauseClock($game);
        }

        $game->current_period++;
        $game->clock_seconds_remaining = $this->periodDuration($game);
    }

    private function finalize(Game $game): void
    {
        $this->requireStatus($game, [GameStatus::Live, GameStatus::Suspended], 'Only a live or suspended game can be finalized.');
        if ($game->home_score === $game->away_score) {
            throw ValidationException::withMessages(['action' => 'A basketball game cannot be finalized with a tied score. Add an overtime period first.']);
        }
        if ($game->clock_running) {
            $this->pauseClock($game);
        }

        $game->status = GameStatus::Final;
        $game->finalized_at = now();
    }

    private function periodDuration(Game $game): int
    {
        $minutes = $game->current_period > $game->season->period_count
            ? $game->season->overtime_minutes
            : $game->season->period_minutes;

        return (int) $minutes * 60;
    }

    private function requireStatus(Game $game, array $allowed, string $message): void
    {
        if (! in_array($game->status, $allowed, true)) {
            throw ValidationException::withMessages(['action' => $message]);
        }
    }

    private function teamRegistrationId(Game $game, array $attributes): ?int
    {
        return match ($attributes['team'] ?? null) {
            'home' => $game->home_team_registration_id,
            'away' => $game->away_team_registration_id,
            default => null,
        };
    }

    private function pointsDelta(array $attributes): ?int
    {
        if (! isset($attributes['points'])) {
            return null;
        }

        return (int) $attributes['points'] * (($attributes['action'] ?? null) === 'correct' ? -1 : 1);
    }

    private function eventType(string $action): string
    {
        return match ($action) {
            'correct' => 'correction', 'clock_adjust' => 'clock_adjustment', default => $action
        };
    }
}
