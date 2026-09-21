<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Competition\UpsertSeasonRequest;
use App\Http\Resources\SeasonResource;
use App\Models\Competition;
use App\Models\Organization;
use App\Models\Season;
use App\Services\CompetitionConfigurationService;

class SeasonController extends Controller
{
    public function __construct(private readonly CompetitionConfigurationService $configuration) {}

    public function store(UpsertSeasonRequest $request, Organization $organization, Competition $competition): SeasonResource
    {
        $this->authorize('manageCompetitions', $organization);
        $season = $this->configuration->createSeason($organization, $competition, $request->validated());

        return new SeasonResource($season->load(['primaryVenue.administrativeArea', 'divisions']));
    }

    public function update(UpsertSeasonRequest $request, Organization $organization, Competition $competition, Season $season): SeasonResource
    {
        $this->authorize('manageCompetitions', $organization);
        $season = $this->configuration->updateSeason($organization, $competition, $season, $request->validated());

        return new SeasonResource($season->refresh()->load(['primaryVenue.administrativeArea', 'divisions']));
    }
}
