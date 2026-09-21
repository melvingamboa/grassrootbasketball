<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'game_id', 'changed_by', 'change_type', 'from_status', 'to_status',
        'old_scheduled_at', 'new_scheduled_at', 'old_venue_id', 'new_venue_id',
        'old_home_score', 'old_away_score', 'new_home_score', 'new_away_score', 'reason',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function oldVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'old_venue_id');
    }

    public function newVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'new_venue_id');
    }

    protected function casts(): array
    {
        return [
            'old_scheduled_at' => 'datetime',
            'new_scheduled_at' => 'datetime',
        ];
    }
}
