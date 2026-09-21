<?php

namespace App\Models;

use App\Enums\VenueStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    protected $fillable = ['organization_id', 'administrative_area_id', 'name', 'slug', 'address', 'latitude', 'longitude', 'status'];

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
        return $this->hasMany(Season::class, 'primary_venue_id');
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    protected function casts(): array
    {
        return ['status' => VenueStatus::class, 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }
}
