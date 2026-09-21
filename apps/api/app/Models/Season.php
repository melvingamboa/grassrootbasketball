<?php

namespace App\Models;

use App\Enums\SeasonFormat;
use App\Enums\SeasonStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    protected $fillable = ['competition_id', 'primary_venue_id', 'name', 'slug', 'starts_on', 'ends_on', 'timezone', 'format', 'status', 'max_roster_size', 'period_count', 'period_minutes', 'overtime_minutes', 'rules_notes'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function primaryVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'primary_venue_id');
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class)->orderBy('name');
    }

    public function teamRegistrations(): HasMany
    {
        return $this->hasMany(SeasonTeamRegistration::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class)->orderBy('scheduled_at')->orderBy('id');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class)->latest('published_at')->latest('id');
    }

    public function standings(): HasMany
    {
        return $this->hasMany(Standing::class)->orderBy('division_id')->orderBy('rank')->orderBy('id');
    }

    public function brackets(): HasMany
    {
        return $this->hasMany(Bracket::class)->orderBy('division_id')->orderBy('name')->orderBy('id');
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'format' => SeasonFormat::class, 'status' => SeasonStatus::class];
    }
}
