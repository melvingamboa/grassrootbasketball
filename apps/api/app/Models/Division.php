<?php

namespace App\Models;

use App\Enums\DivisionCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    protected $fillable = ['season_id', 'name', 'slug', 'category', 'gender', 'minimum_age', 'maximum_age', 'is_active'];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function teamRegistrations(): HasMany
    {
        return $this->hasMany(SeasonTeamRegistration::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function standings(): HasMany
    {
        return $this->hasMany(Standing::class)->orderBy('rank')->orderBy('id');
    }

    public function brackets(): HasMany
    {
        return $this->hasMany(Bracket::class)->orderBy('name')->orderBy('id');
    }

    protected function casts(): array
    {
        return ['category' => DivisionCategory::class, 'is_active' => 'boolean'];
    }
}
