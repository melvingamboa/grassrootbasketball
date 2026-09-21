<?php

namespace App\Models;

use App\Enums\BracketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bracket extends Model
{
    protected $fillable = ['season_id', 'division_id', 'name', 'status', 'created_by', 'updated_by'];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BracketMatch::class)->orderBy('round_number')->orderBy('match_number')->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return ['status' => BracketStatus::class];
    }
}
