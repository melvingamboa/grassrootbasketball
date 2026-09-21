<?php

namespace App\Models;

use App\Enums\GameStatus;
use App\Rules\SupportedLivestreamUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'season_id', 'division_id', 'home_team_registration_id', 'away_team_registration_id',
        'venue_id', 'scheduled_at', 'estimated_duration_minutes', 'round', 'status',
        'home_score', 'away_score', 'status_reason', 'finalized_at', 'created_by', 'updated_by',
        'current_period', 'clock_seconds_remaining', 'clock_running', 'clock_started_at', 'started_at',
        'livestream_url', 'livestream_provider', 'livestream_status',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function homeTeamRegistration(): BelongsTo
    {
        return $this->belongsTo(SeasonTeamRegistration::class, 'home_team_registration_id');
    }

    public function awayTeamRegistration(): BelongsTo
    {
        return $this->belongsTo(SeasonTeamRegistration::class, 'away_team_registration_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(GameChange::class)->latest('created_at')->latest('id');
    }

    public function liveEvents(): HasMany
    {
        return $this->hasMany(GameLiveEvent::class)->latest('created_at')->latest('id');
    }

    public function playerStats(): HasMany
    {
        return $this->hasMany(GamePlayerStat::class)->orderBy('team_registration_id')->orderBy('id');
    }

    public function statEvents(): HasMany
    {
        return $this->hasMany(GameStatEvent::class)->latest('created_at')->latest('id');
    }

    public function effectiveClockSeconds(): int
    {
        if (! $this->clock_running || $this->clock_started_at === null) {
            return (int) $this->clock_seconds_remaining;
        }

        return max(0, (int) $this->clock_seconds_remaining - (int) $this->clock_started_at->diffInSeconds(now()));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function livestreamData(bool $public = false): ?array
    {
        if (! $this->livestream_url || ($public && $this->livestream_status === 'unavailable')) {
            return null;
        }

        $videoId = $this->livestream_provider === 'youtube'
            ? SupportedLivestreamUrl::youtubeVideoId($this->livestream_url)
            : null;

        return [
            'provider' => $this->livestream_provider,
            'provider_label' => match ($this->livestream_provider) {
                'youtube' => 'YouTube',
                'facebook' => 'Facebook',
                default => 'Livestream',
            },
            'status' => $this->livestream_status,
            'status_label' => ucfirst($this->livestream_status),
            'watch_url' => $this->livestream_url,
            'embed_url' => $videoId ? "https://www.youtube-nocookie.com/embed/{$videoId}" : null,
            'can_embed' => $videoId !== null,
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'finalized_at' => 'datetime',
            'clock_running' => 'boolean',
            'clock_started_at' => 'datetime',
            'started_at' => 'datetime',
            'status' => GameStatus::class,
        ];
    }
}
