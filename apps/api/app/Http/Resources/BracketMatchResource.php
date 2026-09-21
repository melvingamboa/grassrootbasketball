<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BracketMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'round_number' => $this->round_number,
            'match_number' => $this->match_number,
            'round_label' => $this->round_label,
            'home_team' => $this->team($this->whenLoaded('homeTeamRegistration')),
            'away_team' => $this->team($this->whenLoaded('awayTeamRegistration')),
            'winner_team' => $this->team($this->whenLoaded('winnerTeamRegistration')),
            'game_id' => $this->game_id,
        ];
    }

    private function team(mixed $registration): ?array
    {
        if (! $registration || ! $registration->relationLoaded('team')) {
            return null;
        }

        $team = $registration->team;

        return [
            'registration_id' => $registration->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'short_name' => $team->short_name,
            'primary_color' => $team->primary_color,
            'secondary_color' => $team->secondary_color,
            'logo_url' => $team->logo_path ? Storage::disk(config('media.disk'))->url($team->logo_path) : null,
        ];
    }
}
