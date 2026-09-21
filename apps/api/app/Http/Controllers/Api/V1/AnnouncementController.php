<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AnnouncementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\UpsertAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Competition;
use App\Models\Organization;
use App\Models\Season;
use App\Services\CompetitionConfigurationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AnnouncementController extends Controller
{
    public function __construct(private readonly CompetitionConfigurationService $configuration) {}

    public function index(Organization $organization, Competition $competition, Season $season): AnonymousResourceCollection
    {
        $this->authorize('view', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);

        return AnnouncementResource::collection($season->announcements()->get());
    }

    public function store(UpsertAnnouncementRequest $request, Organization $organization, Competition $competition, Season $season): AnnouncementResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        $attributes = $request->validated();
        $announcement = $season->announcements()->create([
            ...$attributes,
            'published_at' => $attributes['status'] === AnnouncementStatus::Published->value ? now() : null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return new AnnouncementResource($announcement);
    }

    public function update(UpsertAnnouncementRequest $request, Organization $organization, Competition $competition, Season $season, Announcement $announcement): AnnouncementResource
    {
        $this->authorize('manageCompetitions', $organization);
        $this->ensureAnnouncementBelongsTo($organization, $competition, $season, $announcement);
        $attributes = $request->validated();
        $status = isset($attributes['status']) ? AnnouncementStatus::from($attributes['status']) : $announcement->status;
        $announcement->update([
            ...$attributes,
            'published_at' => $status === AnnouncementStatus::Published
                ? ($announcement->published_at ?? now())
                : null,
            'updated_by' => $request->user()->id,
        ]);

        return new AnnouncementResource($announcement->refresh());
    }

    public function destroy(Organization $organization, Competition $competition, Season $season, Announcement $announcement): Response
    {
        $this->authorize('manageCompetitions', $organization);
        $this->ensureAnnouncementBelongsTo($organization, $competition, $season, $announcement);
        $announcement->delete();

        return response()->noContent();
    }

    private function ensureAnnouncementBelongsTo(Organization $organization, Competition $competition, Season $season, Announcement $announcement): void
    {
        $this->configuration->ensureSeasonBelongsTo($organization, $competition, $season);
        abort_unless($announcement->season_id === $season->id, 404);
    }
}
