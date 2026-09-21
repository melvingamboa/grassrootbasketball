<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameStatEventResource extends JsonResource
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
            'player_registration_id' => $this->player_registration_id,
            'stat' => $this->stat,
            'delta' => $this->delta,
            'value_after' => $this->value_after,
            'reason' => $this->reason,
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy ? [
                'id' => $this->recordedBy->id,
                'name' => $this->recordedBy->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
