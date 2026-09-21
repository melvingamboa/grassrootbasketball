<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\MediaUploadRequest;
use App\Http\Requests\Team\UpsertTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Organization;
use App\Models\Team;
use App\Services\MediaStorageService;
use App\Services\TeamRegistrationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends Controller
{
    public function __construct(private readonly TeamRegistrationService $registrations, private readonly MediaStorageService $media) {}

    public function index(Organization $organization): AnonymousResourceCollection
    {
        $this->authorize('view', $organization);

        return TeamResource::collection($organization->teams()->with('administrativeArea')->orderBy('name')->get());
    }

    public function store(UpsertTeamRequest $request, Organization $organization): TeamResource
    {
        $this->authorize('manageCompetitions', $organization);
        $team = $this->registrations->createTeam($organization, $request->validated());

        return new TeamResource($team->load('administrativeArea'));
    }

    public function update(UpsertTeamRequest $request, Organization $organization, Team $team): TeamResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->registrations->ensureTeamBelongsTo($organization, $team);
        $team->update($request->validated());

        return new TeamResource($team->refresh()->load('administrativeArea'));
    }

    public function uploadLogo(MediaUploadRequest $request, Organization $organization, Team $team): TeamResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->registrations->ensureTeamBelongsTo($organization, $team);
        $team->update(['logo_path' => $this->media->replace($team->logo_path, $request->file('image'), "organizations/{$organization->id}/teams")]);

        return new TeamResource($team->refresh()->load('administrativeArea'));
    }
}
