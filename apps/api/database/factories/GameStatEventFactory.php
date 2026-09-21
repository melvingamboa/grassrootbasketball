<?php

namespace Database\Factories;

use App\Models\GamePlayerStat;
use App\Models\GameStatEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameStatEvent>
 */
class GameStatEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => fn () => GamePlayerStat::query()->firstOrFail()->game_id,
            'game_player_stat_id' => fn () => GamePlayerStat::query()->firstOrFail()->id,
            'player_registration_id' => fn () => GamePlayerStat::query()->firstOrFail()->player_registration_id,
            'stat' => 'rebounds',
            'delta' => 1,
            'value_after' => 1,
        ];
    }
}
