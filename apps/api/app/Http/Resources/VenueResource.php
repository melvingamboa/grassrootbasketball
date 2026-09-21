<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'address' => $this->address, 'latitude' => $this->latitude, 'longitude' => $this->longitude,
            'status' => $this->status->value, 'status_label' => $this->status->label(),
            'administrative_area' => new AdministrativeAreaResource($this->whenLoaded('administrativeArea')),
        ];
    }
}
