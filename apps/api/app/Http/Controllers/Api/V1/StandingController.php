<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Standing\RecalculateStandingsRequest;
use App\Http\Requests\Standing\UpdateStandingsRequest;
use App\Http\Resources\StandingResource;
use App\Models\Competition;
use App\Models\Organization;
use App\Models\Season;
use App\Services\CompetitionConfigurationService;
use App\Services\StandingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StandingController extends Controller
{
    public function __construct(
        private readonly CompetitionConfigurationService $configuration,
        private readonly StandingService $standings,
    ) {}

    public function index(Request $request, Organization $organization, Competition $competition, Season $season): AnonymousResourceCollection
    {
        $this->authorize('view', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        $divisionId = $request->filled('division_id') ? $request->integer('division_id') : null;

        return StandingResource::collection($this->standings->list($season, $divisionId));
    }

    public function update(UpdateStandingsRequest $request, Organization $organization, Competition $competition, Season $season): AnonymousResourceCollection
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);

        return StandingResource::collection($this->standings->updateMetadata($season, $request->validated(), $request->user()));
    }

    public function recalculate(RecalculateStandingsRequest $request, Organization $organization, Competition $competition, Season $season): AnonymousResourceCollection
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);

        return StandingResource::collection($this->standings->recalculate($season, $request->integer('division_id'), $request->user()));
    }
}
