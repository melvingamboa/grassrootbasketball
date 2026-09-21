<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bracket\StoreBracketMatchRequest;
use App\Http\Requests\Bracket\UpdateBracketMatchRequest;
use App\Http\Resources\BracketMatchResource;
use App\Models\Bracket;
use App\Models\BracketMatch;
use App\Models\Competition;
use App\Models\Organization;
use App\Models\Season;
use App\Services\BracketService;
use App\Services\CompetitionConfigurationService;
use Illuminate\Http\Response;

class BracketMatchController extends Controller
{
    public function __construct(
        private readonly CompetitionConfigurationService $configuration,
        private readonly BracketService $brackets,
    ) {}

    public function store(StoreBracketMatchRequest $request, Organization $organization, Competition $competition, Season $season, Bracket $bracket): BracketMatchResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);

        return new BracketMatchResource($this->brackets->createMatch($season, $bracket, $request->validated()));
    }

    public function update(UpdateBracketMatchRequest $request, Organization $organization, Competition $competition, Season $season, Bracket $bracket, BracketMatch $bracketMatch): BracketMatchResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);

        return new BracketMatchResource($this->brackets->updateMatch($season, $bracket, $bracketMatch, $request->validated()));
    }

    public function destroy(Organization $organization, Competition $competition, Season $season, Bracket $bracket, BracketMatch $bracketMatch): Response
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        $this->brackets->deleteMatch($season, $bracket, $bracketMatch);

        return response()->noContent();
    }
}
