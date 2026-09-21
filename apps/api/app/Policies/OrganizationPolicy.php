<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_platform_admin ? true : null;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->roleFor($user, $organization) !== null;
    }

    public function create(User $user): bool
    {
        return (bool) $user->is_platform_admin;
    }

    public function update(User $user, Organization $organization): bool
    {
        return in_array($this->roleFor($user, $organization), [OrganizationRole::Owner, OrganizationRole::Admin], true);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->roleFor($user, $organization) === OrganizationRole::Owner;
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $this->roleFor($user, $organization)?->canManageMembers() ?? false;
    }

    public function manageCompetitions(User $user, Organization $organization): bool
    {
        return $this->roleFor($user, $organization)?->canManageLeagues() ?? false;
    }

    public function scoreGames(User $user, Organization $organization): bool
    {
        return $this->roleFor($user, $organization)?->canScoreGames() ?? false;
    }

    private function roleFor(User $user, Organization $organization): ?OrganizationRole
    {
        return $organization->memberships()
            ->where('user_id', $user->id)
            ->first()
            ?->role;
    }
}
