<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Competition\UpsertDivisionRequest;
use App\Http\Resources\DivisionResource;
use App\Models\Competition;
use App\Models\Division;
use App\Models\Organization;
use App\Models\Season;
use App\Services\CompetitionConfigurationService;
use Illuminate\Http\Response;

class DivisionController extends Controller
{
    public function __construct(private readonly CompetitionConfigurationService $configuration) {}

    public function store(UpsertDivisionRequest $request, Organization $organization, Competition $competition, Season $season): DivisionResource
    {
        $this->authorize('manageCompetitions', $organization);
        $division = $this->configuration->createDivision($organization, $competition, $season, $request->validated());

        return new DivisionResource($division);
    }

    public function update(UpsertDivisionRequest $request, Organization $organization, Competition $competition, Season $season, Division $division): DivisionResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        $this->configuration->ensureDivisionBelongsTo($season, $division);
        $division->update($request->validated());

        return new DivisionResource($division->refresh());
    }

    public function destroy(Organization $organization, Competition $competition, Season $season, Division $division): Response
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        $this->configuration->ensureDivisionBelongsTo($season, $division);
        abort_if($season->divisions()->count() <= 1, 422, 'A season must keep at least one division.');
        $division->delete();

        return response()->noContent();
    }
}
