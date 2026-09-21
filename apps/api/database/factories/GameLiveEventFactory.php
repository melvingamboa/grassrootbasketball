<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameLiveEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameLiveEvent>
 */
class GameLiveEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => fn () => Game::query()->firstOrFail()->id,
            'event_type' => 'score',
            'period_number' => 1,
            'points_delta' => 2,
            'home_score_after' => 2,
            'away_score_after' => 0,
            'clock_seconds_remaining' => 600,
        ];
    }
}
