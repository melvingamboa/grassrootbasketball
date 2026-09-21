<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Competition\UpsertCompetitionRequest;
use App\Http\Resources\CompetitionResource;
use App\Models\Competition;
use App\Models\Organization;
use App\Services\CompetitionConfigurationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompetitionController extends Controller
{
    public function __construct(private readonly CompetitionConfigurationService $configuration) {}

    public function index(Organization $organization): AnonymousResourceCollection
    {
        $this->authorize('view', $organization);

        return CompetitionResource::collection($organization->competitions()->with('administrativeArea')->withCount('seasons')->latest()->get());
    }

    public function show(Organization $organization, Competition $competition): CompetitionResource
    {
        $this->authorize('view', $organization);
        $this->configuration->ensureCompetitionBelongsTo($organization, $competition);

        return new CompetitionResource($competition->load(['administrativeArea', 'seasons.primaryVenue', 'seasons.divisions']));
    }

    public function store(UpsertCompetitionRequest $request, Organization $organization): CompetitionResource
    {
        $this->authorize('manageCompetitions', $organization);
        $competition = $this->configuration->createCompetition($organization, $request->validated());

        return new CompetitionResource($competition->load('administrativeArea')->loadCount('seasons'));
    }

    public function update(UpsertCompetitionRequest $request, Organization $organization, Competition $competition): CompetitionResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureCompetitionBelongsTo($organization, $competition);
        $competition->update($request->validated());

        return new CompetitionResource($competition->refresh()->load(['administrativeArea', 'seasons.primaryVenue', 'seasons.divisions']));
    }
}
