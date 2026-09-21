<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeasonTeamRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'status' => $this->status->value, 'status_label' => $this->status->label(),
            'team' => new TeamResource($this->whenLoaded('team')),
            'division' => new DivisionResource($this->whenLoaded('division')),
            'players' => PlayerRegistrationResource::collection($this->whenLoaded('playerRegistrations')),
            'roster_count' => $this->whenCounted('playerRegistrations'),
        ];
    }
}
