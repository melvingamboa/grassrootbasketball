<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BracketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'division' => new DivisionResource($this->whenLoaded('division')),
            'matches' => BracketMatchResource::collection($this->whenLoaded('matches')),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy ? ['id' => $this->updatedBy->id, 'name' => $this->updatedBy->name] : null),
        ];
    }
}
