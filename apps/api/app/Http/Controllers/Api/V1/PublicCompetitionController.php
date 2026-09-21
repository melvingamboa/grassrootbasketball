<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AnnouncementStatus;
use App\Enums\GameStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\ListGamesRequest;
use App\Http\Resources\PublicCompetitionResource;
use App\Http\Resources\PublicGameResource;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Organization;
use App\Models\Season;
use App\Services\BracketService;
use App\Services\CompetitionConfigurationService;
use App\Services\GameSchedulingService;
use App\Services\StandingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicCompetitionController extends Controller
{
    public function __construct(
        private readonly CompetitionConfigurationService $configuration,
        private readonly GameSchedulingService $scheduling,
        private readonly StandingService $standings,
        private readonly BracketService $brackets,
    ) {}

    public function show(Organization $organization, Competition $competition, Season $season): PublicCompetitionResource
    {
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        $relations = ['division', 'venue', 'homeTeamRegistration.team', 'awayTeamRegistration.team'];
        $liveGames = $season->games()->whereIn('status', [GameStatus::Live, GameStatus::Delayed, GameStatus::Suspended])
            ->with($relations)->orderBy('scheduled_at')->limit(6)->get();
        $upcomingGames = $season->games()->whereIn('status', [GameStatus::Scheduled, GameStatus::Postponed])
            ->where('scheduled_at', '>=', now())->with($relations)->orderBy('scheduled_at')->limit(6)->get();
        $recentResults = $season->games()->where('status', GameStatus::Final)
            ->with($relations)->latest('scheduled_at')->limit(6)->get();
        $announcements = $season->announcements()->where('status', AnnouncementStatus::Published)
            ->where('published_at', '<=', now())->limit(6)->get();

        return new PublicCompetitionResource([
            'organization' => ['name' => $organization->name, 'slug' => $organization->slug],
            'competition' => ['name' => $competition->name, 'slug' => $competition->slug, 'description' => $competition->description],
            'season' => ['name' => $season->name, 'slug' => $season->slug, 'timezone' => $season->timezone],
            'live_games' => $liveGames,
            'upcoming_games' => $upcomingGames,
            'recent_results' => $recentResults,
            'announcements' => $announcements,
            'standings' => $this->standings->list($season),
            'brackets' => $this->brackets->list($season, publishedOnly: true),
        ]);
    }

    public function games(ListGamesRequest $request, Organization $organization, Competition $competition, Season $season): AnonymousResourceCollection
    {
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);

        return PublicGameResource::collection($this->scheduling->list($season, $request->validated()));
    }

    public function game(Organization $organization, Competition $competition, Season $season, Game $game): PublicGameResource
    {
        $this->scheduling->ensureGameBelongsTo($organization, $competition, $season, $game);

        return new PublicGameResource($this->scheduling->loadGame($game));
    }
}
