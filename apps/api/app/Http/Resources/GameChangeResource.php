<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameChangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'change_type' => $this->change_type,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'old_scheduled_at' => $this->old_scheduled_at?->toIso8601String(),
            'new_scheduled_at' => $this->new_scheduled_at?->toIso8601String(),
            'old_venue' => new VenueResource($this->whenLoaded('oldVenue')),
            'new_venue' => new VenueResource($this->whenLoaded('newVenue')),
            'old_home_score' => $this->old_home_score,
            'old_away_score' => $this->old_away_score,
            'new_home_score' => $this->new_home_score,
            'new_away_score' => $this->new_away_score,
            'reason' => $this->reason,
            'changed_by' => $this->whenLoaded('changedBy', fn (): ?array => $this->changedBy ? [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
