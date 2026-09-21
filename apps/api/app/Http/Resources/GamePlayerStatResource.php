<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GamePlayerStatResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $registration = $this->resource->relationLoaded('playerRegistration') ? $this->resource->playerRegistration : null;

        return [
            'id' => $this->id,
            'team_registration_id' => $this->team_registration_id,
            'is_starter' => $this->is_starter,
            'is_on_court' => $this->is_on_court,
            'court_slot' => $this->court_slot,
            'player_registration' => $registration && $registration->relationLoaded('player') ? [
                'id' => $registration->id,
                'jersey_number' => $registration->jersey_number,
                'position' => $registration->position->value,
                'position_label' => $registration->position->label(),
                'player' => [
                    'id' => $registration->player->id,
                    'display_name' => $registration->player->display_name,
                ],
            ] : null,
            'points' => $this->points,
            'rebounds' => $this->rebounds,
            'assists' => $this->assists,
            'steals' => $this->steals,
            'blocks' => $this->blocks,
            'turnovers' => $this->turnovers,
            'fouls' => $this->fouls,
        ];
    }
}
