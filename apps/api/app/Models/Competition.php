<?php

namespace App\Models;

use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    protected $fillable = ['organization_id', 'administrative_area_id', 'name', 'slug', 'type', 'status', 'description'];

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

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class)->latest('starts_on');
    }

    protected function casts(): array
    {
        return ['type' => CompetitionType::class, 'status' => CompetitionStatus::class];
    }
}
