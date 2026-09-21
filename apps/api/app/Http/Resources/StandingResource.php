<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class StandingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $team = $this->teamRegistration->team;

        return [
            'id' => $this->id,
            'rank' => $this->rank,
            'played' => $this->played,
            'wins' => $this->wins,
            'losses' => $this->losses,
            'points_for' => $this->points_for,
            'points_against' => $this->points_against,
            'point_difference' => $this->points_for - $this->points_against,
            'qualification_status' => $this->qualification_status->value,
            'qualification_status_label' => $this->qualification_status->label(),
            'notes' => $this->notes,
            'team_registration_id' => $this->team_registration_id,
            'team' => [
                'name' => $team->name,
                'slug' => $team->slug,
                'short_name' => $team->short_name,
                'primary_color' => $team->primary_color,
                'secondary_color' => $team->secondary_color,
                'logo_url' => $team->logo_path ? Storage::disk(config('media.disk'))->url($team->logo_path) : null,
            ],
            'division' => new DivisionResource($this->whenLoaded('division')),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy ? ['id' => $this->updatedBy->id, 'name' => $this->updatedBy->name] : null),
        ];
    }
}
