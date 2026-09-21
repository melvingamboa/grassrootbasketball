<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\MediaUploadRequest;
use App\Http\Requests\Team\UpsertPlayerRequest;
use App\Http\Resources\PlayerResource;
use App\Models\Organization;
use App\Models\Player;
use App\Services\MediaStorageService;
use App\Services\TeamRegistrationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlayerController extends Controller
{
    public function __construct(private readonly TeamRegistrationService $registrations, private readonly MediaStorageService $media) {}

    public function index(Organization $organization): AnonymousResourceCollection
    {
        $this->authorize('view', $organization);

        return PlayerResource::collection($organization->players()->orderBy('last_name')->orderBy('first_name')->get());
    }

    public function store(UpsertPlayerRequest $request, Organization $organization): PlayerResource
    {
        $this->authorize('manageCompetitions', $organization);

        return new PlayerResource($organization->players()->create($request->validated()));
    }

    public function update(UpsertPlayerRequest $request, Organization $organization, Player $player): PlayerResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->registrations->ensurePlayerBelongsTo($organization, $player);
        $player->update($request->validated());

        return new PlayerResource($player->refresh());
    }

    public function uploadPhoto(MediaUploadRequest $request, Organization $organization, Player $player): PlayerResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->registrations->ensurePlayerBelongsTo($organization, $player);
        $player->update(['photo_path' => $this->media->replace($player->photo_path, $request->file('image'), "organizations/{$organization->id}/players")]);

        return new PlayerResource($player->refresh());
    }
}
