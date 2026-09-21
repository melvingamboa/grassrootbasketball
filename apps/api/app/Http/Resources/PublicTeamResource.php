<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PublicTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name, 'slug' => $this->slug, 'short_name' => $this->short_name,
            'primary_color' => $this->primary_color, 'secondary_color' => $this->secondary_color,
            'logo_url' => $this->logo_path ? Storage::disk(config('media.disk'))->url($this->logo_path) : null,
            'location' => $this->administrativeArea?->name,
            'organization' => ['name' => $this->organization->name, 'slug' => $this->organization->slug],
            'participations' => $this->seasonRegistrations->map(fn ($registration): array => [
                'id' => $registration->id,
                'competition' => $registration->season->competition->name,
                'season' => $registration->season->name,
                'division' => $registration->division->name,
                'roster' => PublicPlayerRegistrationResource::collection($registration->playerRegistrations),
            ]),
        ];
    }
}
