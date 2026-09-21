<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    protected $fillable = ['organization_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'contact_email', 'contact_phone', 'photo_path', 'is_active'];

    protected $appends = ['display_name'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(PlayerRegistration::class);
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => collect([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])->filter()->implode(' '));
    }

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'is_active' => 'boolean'];
    }
}
