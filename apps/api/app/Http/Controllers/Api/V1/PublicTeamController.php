<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RegistrationStatus;
use App\Enums\TeamStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicTeamResource;
use App\Models\Organization;
use App\Models\Team;

class PublicTeamController extends Controller
{
    public function show(Organization $organization, Team $team): PublicTeamResource
    {
        abort_unless($team->organization_id === $organization->id && $team->status === TeamStatus::Active, 404);
        $team->load([
            'organization', 'administrativeArea',
            'seasonRegistrations' => fn ($query) => $query
                ->where('status', RegistrationStatus::Approved->value)
                ->with([
                    'season.competition', 'division',
                    'playerRegistrations' => fn ($roster) => $roster
                        ->where('status', RegistrationStatus::Approved->value)
                        ->whereHas('player', fn ($players) => $players->where('is_active', true))
                        ->with('player'),
                ]),
        ]);

        return new PublicTeamResource($team);
    }
}
