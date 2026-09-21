<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreSeasonTeamRegistrationRequest;
use App\Http\Requests\Team\UpdateSeasonTeamRegistrationRequest;
use App\Http\Resources\SeasonTeamRegistrationResource;
use App\Models\Competition;
use App\Models\Organization;
use App\Models\Season;
use App\Models\SeasonTeamRegistration;
use App\Services\CompetitionConfigurationService;
use App\Services\TeamRegistrationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SeasonTeamRegistrationController extends Controller
{
    public function __construct(private readonly CompetitionConfigurationService $configuration, private readonly TeamRegistrationService $registrations) {}

    public function index(Organization $organization, Competition $competition, Season $season): AnonymousResourceCollection
    {
        $this->authorize('view', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);

        return SeasonTeamRegistrationResource::collection($season->teamRegistrations()->with(['team.administrativeArea', 'division', 'playerRegistrations.player'])->withCount('playerRegistrations')->get());
    }

    public function store(StoreSeasonTeamRegistrationRequest $request, Organization $organization, Competition $competition, Season $season): SeasonTeamRegistrationResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        $registration = $this->registrations->registerTeam($organization, $season, $request->validated());

        return new SeasonTeamRegistrationResource($registration->load(['team.administrativeArea', 'division', 'playerRegistrations.player'])->loadCount('playerRegistrations'));
    }

    public function update(UpdateSeasonTeamRegistrationRequest $request, Organization $organization, Competition $competition, Season $season, SeasonTeamRegistration $teamRegistration): SeasonTeamRegistrationResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        $this->registrations->ensureTeamRegistrationBelongsTo($season, $teamRegistration);
        if ($request->filled('division_id')) {
            abort_unless($season->divisions()->whereKey($request->integer('division_id'))->exists(), 404);
        }
        $teamRegistration->update($request->validated());

        return new SeasonTeamRegistrationResource($teamRegistration->refresh()->load(['team.administrativeArea', 'division', 'playerRegistrations.player'])->loadCount('playerRegistrations'));
    }

    public function destroy(Organization $organization, Competition $competition, Season $season, SeasonTeamRegistration $teamRegistration): Response
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        $this->registrations->removeTeamRegistration($season, $teamRegistration);

        return response()->noContent();
    }
}
