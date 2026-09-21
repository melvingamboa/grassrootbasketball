<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\GameLiveUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\SubstituteGamePlayerRequest;
use App\Http\Requests\Game\UpdateGameLineupRequest;
use App\Http\Requests\Game\UpdatePlayerStatRequest;
use App\Http\Resources\GameResource;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Organization;
use App\Models\Season;
use App\Services\GameBoxScoreService;
use App\Services\GameSchedulingService;

class GameBoxScoreController extends Controller
{
    public function __construct(
        private readonly GameSchedulingService $scheduling,
        private readonly GameBoxScoreService $boxScores,
    ) {}

    public function lineup(UpdateGameLineupRequest $request, Organization $organization, Competition $competition, Season $season, Game $game): GameResource
    {
        $this->authorize('scoreGames', $organization);
        $this->scheduling->ensureGameBelongsTo($organization, $competition, $season, $game);
        $game = $this->boxScores->updateLineup($game, $request->validated(), $request->user());
        GameLiveUpdated::dispatch($game);

        return new GameResource($game);
    }

    public function update(UpdatePlayerStatRequest $request, Organization $organization, Competition $competition, Season $season, Game $game, GamePlayerStat $playerStat): GameResource
    {
        $this->authorize('scoreGames', $organization);
        $this->scheduling->ensureGameBelongsTo($organization, $competition, $season, $game);
        $game = $this->boxScores->updateStat($game, $playerStat, $request->validated(), $request->user());
        GameLiveUpdated::dispatch($game);

        return new GameResource($game);
    }

    public function substitute(SubstituteGamePlayerRequest $request, Organization $organization, Competition $competition, Season $season, Game $game): GameResource
    {
        $this->authorize('scoreGames', $organization);
        $this->scheduling->ensureGameBelongsTo($organization, $competition, $season, $game);
        $game = $this->boxScores->substitute($game, $request->validated(), $request->user());
        GameLiveUpdated::dispatch($game);

        return new GameResource($game);
    }
}
