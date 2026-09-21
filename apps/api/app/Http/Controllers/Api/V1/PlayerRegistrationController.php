<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\CreateAndRegisterPlayerRequest;
use App\Http\Requests\Team\UpsertPlayerRegistrationRequest;
use App\Http\Resources\PlayerRegistrationResource;
use App\Models\Competition;
use App\Models\Organization;
use App\Models\PlayerRegistration;
use App\Models\Season;
use App\Models\SeasonTeamRegistration;
use App\Services\CompetitionConfigurationService;
use App\Services\TeamRegistrationService;
use Illuminate\Http\Response;

class PlayerRegistrationController extends Controller
{
    public function __construct(private readonly CompetitionConfigurationService $configuration, private readonly TeamRegistrationService $registrations) {}

    public function store(UpsertPlayerRegistrationRequest $request, Organization $organization, Competition $competition, Season $season, SeasonTeamRegistration $teamRegistration): PlayerRegistrationResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        $registration = $this->registrations->addPlayer($organization, $season, $teamRegistration, $request->validated());

        return new PlayerRegistrationResource($registration->load('player'));
    }

    public function create(CreateAndRegisterPlayerRequest $request, Organization $organization, Competition $competition, Season $season, SeasonTeamRegistration $teamRegistration): PlayerRegistrationResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        $registration = $this->registrations->createAndAddPlayer($organization, $season, $teamRegistration, $request->validated());

        return new PlayerRegistrationResource($registration->load('player'));
    }

    public function update(UpsertPlayerRegistrationRequest $request, Organization $organization, Competition $competition, Season $season, SeasonTeamRegistration $teamRegistration, PlayerRegistration $playerRegistration): PlayerRegistrationResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        $this->registrations->ensureTeamRegistrationBelongsTo($season, $teamRegistration);
        $registration = $this->registrations->updatePlayerRegistration($teamRegistration, $playerRegistration, $request->validated());

        return new PlayerRegistrationResource($registration->refresh()->load('player'));
    }

    public function destroy(Organization $organization, Competition $competition, Season $season, SeasonTeamRegistration $teamRegistration, PlayerRegistration $playerRegistration): Response
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        $this->registrations->ensureTeamRegistrationBelongsTo($season, $teamRegistration);
        $this->registrations->ensurePlayerRegistrationBelongsTo($teamRegistration, $playerRegistration);
        $playerRegistration->delete();

        return response()->noContent();
    }
}
