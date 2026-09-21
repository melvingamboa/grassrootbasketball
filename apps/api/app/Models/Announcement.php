<?php

namespace App\Models;

use App\Enums\AnnouncementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = ['season_id', 'title', 'body', 'status', 'published_at', 'created_by', 'updated_by'];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
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
        return [
            'status' => AnnouncementStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
