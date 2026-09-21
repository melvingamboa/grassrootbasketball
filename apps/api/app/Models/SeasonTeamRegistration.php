<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeasonTeamRegistration extends Model
{
    protected $fillable = ['season_id', 'division_id', 'team_id', 'status'];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function playerRegistrations(): HasMany
    {
        return $this->hasMany(PlayerRegistration::class);
    }

    public function homeGames(): HasMany
    {
        return $this->hasMany(Game::class, 'home_team_registration_id');
    }

    public function awayGames(): HasMany
    {
        return $this->hasMany(Game::class, 'away_team_registration_id');
    }

    public function standings(): HasMany
    {
        return $this->hasMany(Standing::class, 'team_registration_id');
    }

    protected function casts(): array
    {
        return ['status' => RegistrationStatus::class];
    }
}
