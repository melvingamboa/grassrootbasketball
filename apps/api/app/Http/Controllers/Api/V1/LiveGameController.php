<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\GameLiveUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\LiveGameActionRequest;
use App\Http\Resources\GameResource;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Organization;
use App\Models\Season;
use App\Services\GameSchedulingService;
use App\Services\LiveGameService;

class LiveGameController extends Controller
{
    public function __construct(
        private readonly GameSchedulingService $scheduling,
        private readonly LiveGameService $liveGames,
    ) {}

    public function store(LiveGameActionRequest $request, Organization $organization, Competition $competition, Season $season, Game $game): GameResource
    {
        $this->authorize('scoreGames', $organization);
        $this->scheduling->ensureGameBelongsTo($organization, $competition, $season, $game);

        $game = $this->liveGames->apply($game, $request->validated(), $request->user());
        GameLiveUpdated::dispatch($game);

        return new GameResource($game);
    }
}
