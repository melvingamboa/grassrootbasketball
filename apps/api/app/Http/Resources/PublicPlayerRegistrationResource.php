<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PublicPlayerRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->player->display_name,
            'photo_url' => $this->player->photo_path ? Storage::disk(config('media.disk'))->url($this->player->photo_path) : null,
            'jersey_number' => $this->jersey_number,
            'position' => $this->position->value,
            'position_label' => $this->position->label(),
        ];
    }
}
