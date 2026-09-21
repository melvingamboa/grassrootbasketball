<?php

namespace App\Models;

use Database\Factories\GameStatEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameStatEvent extends Model
{
    /** @use HasFactory<GameStatEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'game_id', 'game_player_stat_id', 'player_registration_id', 'stat',
        'delta', 'value_after', 'reason', 'recorded_by',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePlayerStat(): BelongsTo
    {
        return $this->belongsTo(GamePlayerStat::class);
    }

    public function playerRegistration(): BelongsTo
    {
        return $this->belongsTo(PlayerRegistration::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
