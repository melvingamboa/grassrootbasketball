<?php

namespace App\Models;

use App\Enums\AdministrativeAreaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdministrativeArea extends Model
{
    protected $fillable = ['parent_id', 'type', 'name', 'code'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    protected function casts(): array
    {
        return ['type' => AdministrativeAreaType::class];
    }
}
