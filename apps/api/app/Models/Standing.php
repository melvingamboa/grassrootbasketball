<?php

namespace App\Models;

use App\Enums\StandingQualificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Standing extends Model
{
    protected $fillable = [
        'season_id', 'division_id', 'team_registration_id', 'rank', 'played', 'wins', 'losses',
        'points_for', 'points_against', 'qualification_status', 'notes', 'updated_by',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function teamRegistration(): BelongsTo
    {
        return $this->belongsTo(SeasonTeamRegistration::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return ['qualification_status' => StandingQualificationStatus::class];
    }
}
