<?php

namespace App\Models;

use Database\Factories\GameLiveEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameLiveEvent extends Model
{
    /** @use HasFactory<GameLiveEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'game_id', 'event_type', 'team_registration_id', 'period_number', 'points_delta',
        'home_score_after', 'away_score_after', 'clock_seconds_remaining', 'details', 'recorded_by',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function teamRegistration(): BelongsTo
    {
        return $this->belongsTo(SeasonTeamRegistration::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
