<?php

namespace App\Models;

use Database\Factories\GamePlayerStatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamePlayerStat extends Model
{
    /** @use HasFactory<GamePlayerStatFactory> */
    use HasFactory;

    protected $fillable = [
        'game_id', 'player_registration_id', 'team_registration_id', 'is_starter', 'is_on_court', 'court_slot',
        'points', 'rebounds', 'assists', 'steals', 'blocks', 'turnovers', 'fouls',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function playerRegistration(): BelongsTo
    {
        return $this->belongsTo(PlayerRegistration::class);
    }

    public function teamRegistration(): BelongsTo
    {
        return $this->belongsTo(SeasonTeamRegistration::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(GameStatEvent::class)->latest('created_at')->latest('id');
    }

    protected function casts(): array
    {
        return ['is_starter' => 'boolean', 'is_on_court' => 'boolean', 'court_slot' => 'integer'];
    }
}
