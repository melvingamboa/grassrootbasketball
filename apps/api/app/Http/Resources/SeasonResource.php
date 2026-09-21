<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeasonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'starts_on' => $this->starts_on?->toDateString(), 'ends_on' => $this->ends_on?->toDateString(),
            'timezone' => $this->timezone, 'format' => $this->format->value, 'format_label' => $this->format->label(),
            'status' => $this->status->value, 'status_label' => $this->status->label(),
            'max_roster_size' => $this->max_roster_size, 'period_count' => $this->period_count,
            'period_minutes' => $this->period_minutes, 'overtime_minutes' => $this->overtime_minutes,
            'rules_notes' => $this->rules_notes,
            'primary_venue' => new VenueResource($this->whenLoaded('primaryVenue')),
            'divisions' => DivisionResource::collection($this->whenLoaded('divisions')),
        ];
    }
}
