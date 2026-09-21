<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\PlayerRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GamePlayerStat>
 */
class GamePlayerStatFactory extends Factory
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
            'player_registration_id' => fn () => PlayerRegistration::query()->firstOrFail()->id,
            'team_registration_id' => fn (array $attributes) => PlayerRegistration::query()->findOrFail($attributes['player_registration_id'])->season_team_registration_id,
        ];
    }
}
