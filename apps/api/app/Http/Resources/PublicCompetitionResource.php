<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCompetitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'organization' => $this->resource['organization'],
            'competition' => $this->resource['competition'],
            'season' => $this->resource['season'],
            'live_games' => PublicGameResource::collection($this->resource['live_games']),
            'upcoming_games' => PublicGameResource::collection($this->resource['upcoming_games']),
            'recent_results' => PublicGameResource::collection($this->resource['recent_results']),
            'announcements' => AnnouncementResource::collection($this->resource['announcements']),
            'standings' => StandingResource::collection($this->resource['standings']),
            'brackets' => BracketResource::collection($this->resource['brackets']),
        ];
    }
}
