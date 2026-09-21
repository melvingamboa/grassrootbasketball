<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\ListGamesRequest;
use App\Http\Requests\Game\StoreGameRequest;
use App\Http\Requests\Game\UpdateGameRequest;
use App\Http\Resources\GameResource;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Organization;
use App\Models\Season;
use App\Services\CompetitionConfigurationService;
use App\Services\GameSchedulingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GameController extends Controller
{
    public function __construct(
        private readonly CompetitionConfigurationService $configuration,
        private readonly GameSchedulingService $scheduling,
    ) {}

    public function index(ListGamesRequest $request, Organization $organization, Competition $competition, Season $season): AnonymousResourceCollection
    {
        $this->authorize('view', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);

        return GameResource::collection($this->scheduling->list($season, $request->validated()));
    }

    public function store(StoreGameRequest $request, Organization $organization, Competition $competition, Season $season): GameResource
    {
        $this->authorize('manageCompetitions', $organization);
        $game = $this->scheduling->create($organization, $competition, $season, $request->validated(), $request->user());

        return new GameResource($game);
    }

    public function show(Organization $organization, Competition $competition, Season $season, Game $game): GameResource
    {
        $this->authorize('view', $organization);
        $this->scheduling->ensureGameBelongsTo($organization, $competition, $season, $game);

        return new GameResource($this->scheduling->loadGame($game));
    }

    public function update(UpdateGameRequest $request, Organization $organization, Competition $competition, Season $season, Game $game): GameResource
    {
        $this->authorize('manageCompetitions', $organization);
        $game = $this->scheduling->update($organization, $competition, $season, $game, $request->validated(), $request->user());

        return new GameResource($game);
    }
}
