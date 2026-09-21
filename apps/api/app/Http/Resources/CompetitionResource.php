<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompetitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'type' => $this->type->value, 'type_label' => $this->type->label(),
            'status' => $this->status->value, 'status_label' => $this->status->label(),
            'description' => $this->description,
            'administrative_area' => new AdministrativeAreaResource($this->whenLoaded('administrativeArea')),
            'seasons' => SeasonResource::collection($this->whenLoaded('seasons')),
            'season_count' => $this->whenCounted('seasons'),
        ];
    }
}
