<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Competition\UpsertVenueRequest;
use App\Http\Resources\VenueResource;
use App\Models\Organization;
use App\Models\Venue;
use App\Services\CompetitionConfigurationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VenueController extends Controller
{
    public function __construct(private readonly CompetitionConfigurationService $configuration) {}

    public function index(Organization $organization): AnonymousResourceCollection
    {
        $this->authorize('view', $organization);

        return VenueResource::collection($organization->venues()->with('administrativeArea')->orderBy('name')->get());
    }

    public function store(UpsertVenueRequest $request, Organization $organization): VenueResource
    {
        $this->authorize('manageCompetitions', $organization);
        $venue = $this->configuration->createVenue($organization, $request->validated());

        return new VenueResource($venue->load('administrativeArea'));
    }

    public function update(UpsertVenueRequest $request, Organization $organization, Venue $venue): VenueResource
    {
        $this->authorize('manageCompetitions', $organization);
        abort_unless($venue->organization_id === $organization->id, 404);
        $venue->update($request->validated());

        return new VenueResource($venue->refresh()->load('administrativeArea'));
    }
}
