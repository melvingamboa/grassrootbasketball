<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PublicGameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheduled_at' => $this->scheduled_at->toIso8601String(),
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'round' => $this->round,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_reason' => $this->status_reason,
            'livestream' => $this->livestreamData(public: true),
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'current_period' => $this->current_period,
            'period_label' => $this->periodLabel(),
            'clock_seconds_remaining' => $this->effectiveClockSeconds(),
            'clock_running' => $this->clock_running && $this->effectiveClockSeconds() > 0,
            'started_at' => $this->started_at?->toIso8601String(),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'division' => new DivisionResource($this->whenLoaded('division')),
            'venue' => new VenueResource($this->whenLoaded('venue')),
            'home_team' => $this->publicTeam($this->whenLoaded('homeTeamRegistration')),
            'away_team' => $this->publicTeam($this->whenLoaded('awayTeamRegistration')),
            'schedule_history' => $this->whenLoaded('changes', fn () => $this->changes
                ->whereIn('change_type', ['schedule', 'status'])
                ->map(fn ($change): array => [
                    'change_type' => $change->change_type,
                    'from_status' => $change->from_status,
                    'to_status' => $change->to_status,
                    'old_scheduled_at' => $change->old_scheduled_at?->toIso8601String(),
                    'new_scheduled_at' => $change->new_scheduled_at?->toIso8601String(),
                    'reason' => $change->reason,
                    'created_at' => $change->created_at?->toIso8601String(),
                ])),
            'live_events' => PublicGameLiveEventResource::collection($this->whenLoaded('liveEvents')),
            'period_scores' => $this->periodScores(),
            'box_score' => GamePlayerStatResource::collection($this->whenLoaded('playerStats')),
        ];
    }

    private function periodLabel(): string
    {
        $periodCount = $this->resource->relationLoaded('season') ? (int) $this->resource->season->period_count : 4;

        return $this->current_period > $periodCount ? 'OT'.($this->current_period - $periodCount > 1 ? $this->current_period - $periodCount : '') : 'Q'.$this->current_period;
    }

    private function periodScores(): array
    {
        if (! $this->relationLoaded('liveEvents')) {
            return [];
        }

        return $this->liveEvents->whereIn('event_type', ['score', 'correction'])->groupBy('period_number')->map(fn ($events, $period) => [
            'period' => (int) $period,
            'home' => $events->where('team_registration_id', $this->home_team_registration_id)->sum('points_delta'),
            'away' => $events->where('team_registration_id', $this->away_team_registration_id)->sum('points_delta'),
        ])->sortBy('period')->values()->all();
    }

    private function publicTeam(mixed $registration): mixed
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
