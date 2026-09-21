<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'jersey_number' => $this->jersey_number,
            'position' => $this->position->value, 'position_label' => $this->position->label(),
            'status' => $this->status->value, 'status_label' => $this->status->label(),
            'player' => new PlayerResource($this->whenLoaded('player')),
        ];
    }
}
