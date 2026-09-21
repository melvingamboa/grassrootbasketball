<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bracket\InitializeBracketRequest;
use App\Http\Requests\Bracket\StoreBracketRequest;
use App\Http\Requests\Bracket\UpdateBracketRequest;
use App\Http\Resources\BracketResource;
use App\Models\Bracket;
use App\Models\Competition;
use App\Models\Organization;
use App\Models\Season;
use App\Services\BracketService;
use App\Services\CompetitionConfigurationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BracketController extends Controller
{
    public function __construct(
        private readonly CompetitionConfigurationService $configuration,
        private readonly BracketService $brackets,
    ) {}

    public function index(Organization $organization, Competition $competition, Season $season): AnonymousResourceCollection
    {
        $this->authorize('view', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);

        return BracketResource::collection($this->brackets->list($season));
    }

    public function store(StoreBracketRequest $request, Organization $organization, Competition $competition, Season $season): BracketResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);

        return new BracketResource($this->brackets->create($season, $request->validated(), $request->user()));
    }

    public function update(UpdateBracketRequest $request, Organization $organization, Competition $competition, Season $season, Bracket $bracket): BracketResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);

        return new BracketResource($this->brackets->update($season, $bracket, $request->validated(), $request->user()));
    }

    public function initialize(InitializeBracketRequest $request, Organization $organization, Competition $competition, Season $season, Bracket $bracket): BracketResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);

        return new BracketResource($this->brackets->initialize($season, $bracket, $request->string('template')->toString()));
    }

    public function destroy(Organization $organization, Competition $competition, Season $season, Bracket $bracket): Response
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        $this->brackets->delete($season, $bracket);

        return response()->noContent();
    }
}
