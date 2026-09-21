<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'display_name' => $this->display_name,
            'first_name' => $this->first_name, 'middle_name' => $this->middle_name,
            'last_name' => $this->last_name, 'suffix' => $this->suffix,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'contact_email' => $this->contact_email, 'contact_phone' => $this->contact_phone,
            'photo_url' => $this->photo_path ? Storage::disk(config('media.disk'))->url($this->photo_path) : null,
            'is_active' => $this->is_active,
        ];
    }
}
