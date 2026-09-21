<?php

namespace App\Models;

use App\Enums\TeamStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = ['organization_id', 'administrative_area_id', 'name', 'slug', 'short_name', 'primary_color', 'secondary_color', 'logo_path', 'status'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function administrativeArea(): BelongsTo
    {
        return $this->belongsTo(AdministrativeArea::class);
    }

    public function seasonRegistrations(): HasMany
    {
        return $this->hasMany(SeasonTeamRegistration::class);
    }

    protected function casts(): array
    {
        return ['status' => TeamStatus::class];
    }
}
