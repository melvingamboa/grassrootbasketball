<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicGameLiveEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'team_registration_id' => $this->team_registration_id,
            'period_number' => $this->period_number,
            'points_delta' => $this->points_delta,
            'home_score_after' => $this->home_score_after,
            'away_score_after' => $this->away_score_after,
            'clock_seconds_remaining' => $this->clock_seconds_remaining,
            'details' => collect($this->details ?? [])->only([
                'reason', 'player_registration_id', 'player_out_registration_id',
                'player_in_registration_id', 'court_slot', 'stat', 'delta',
                'value_after', 'clock_seconds_before', 'clock_seconds_after',
            ])->all(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
