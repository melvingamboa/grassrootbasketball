<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'short_name' => $this->short_name,
            'primary_color' => $this->primary_color, 'secondary_color' => $this->secondary_color,
            'logo_url' => $this->logo_path ? Storage::disk(config('media.disk'))->url($this->logo_path) : null,
            'status' => $this->status->value, 'status_label' => $this->status->label(),
            'administrative_area' => new AdministrativeAreaResource($this->whenLoaded('administrativeArea')),
        ];
    }
}
