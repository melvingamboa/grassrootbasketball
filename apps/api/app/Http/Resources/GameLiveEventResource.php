<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameLiveEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'team_registration_id' => $this->team_registration_id,
            'period_number' => $this->period_number,
            'points_delta' => $this->points_delta,
            'home_score_after' => $this->home_score_after,
            'away_score_after' => $this->away_score_after,
            'clock_seconds_remaining' => $this->clock_seconds_remaining,
            'details' => $this->details,
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy ? [
                'id' => $this->recordedBy->id,
                'name' => $this->recordedBy->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
