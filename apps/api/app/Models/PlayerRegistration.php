<?php

namespace App\Models;

use App\Enums\PlayerPosition;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlayerRegistration extends Model
{
    protected $fillable = ['season_team_registration_id', 'player_id', 'jersey_number', 'position', 'status'];

    public function teamRegistration(): BelongsTo
    {
        return $this->belongsTo(SeasonTeamRegistration::class, 'season_team_registration_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function gameStats(): HasMany
    {
        return $this->hasMany(GamePlayerStat::class);
    }

    protected function casts(): array
    {
        return ['position' => PlayerPosition::class, 'status' => RegistrationStatus::class];
    }
}
