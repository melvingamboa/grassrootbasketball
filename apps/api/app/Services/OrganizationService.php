<?php

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    public function create(User $user, array $attributes): Organization
    {
        return DB::transaction(function () use ($user, $attributes): Organization {
            $organization = Organization::create([
                ...$attributes,
                'slug' => $this->uniqueSlug($attributes['name']),
                'created_by' => $user->id,
            ]);

            $organization->memberships()->create([
                'user_id' => $user->id,
                'role' => OrganizationRole::Owner,
                'joined_at' => now(),
            ]);

            return $organization;
        });
    }

    public function updateMembershipRole(
        User $actor,
        Organization $organization,
        OrganizationMembership $membership,
        OrganizationRole $newRole,
    ): OrganizationMembership {
        $this->ensureMembershipBelongsToOrganization($organization, $membership);
        $this->ensureActorCanChangeRole($actor, $organization, $membership, $newRole);

        return DB::transaction(function () use ($organization, $membership, $newRole): OrganizationMembership {
            if ($membership->role === OrganizationRole::Owner && $newRole !== OrganizationRole::Owner) {
                $this->ensureAnotherOwnerExists($organization);
            }

            $membership->update(['role' => $newRole]);

            return $membership->refresh()->load('user');
        });
    }

    public function removeMembership(
        User $actor,
        Organization $organization,
        OrganizationMembership $membership,
    ): void {
        $this->ensureMembershipBelongsToOrganization($organization, $membership);
        $this->ensureActorCanChangeRole($actor, $organization, $membership, $membership->role);

        DB::transaction(function () use ($organization, $membership): void {
            if ($membership->role === OrganizationRole::Owner) {
                $this->ensureAnotherOwnerExists($organization);
            }

            $membership->delete();
        });
    }

    private function ensureMembershipBelongsToOrganization(
        Organization $organization,
        OrganizationMembership $membership,
    ): void {
        abort_unless($membership->organization_id === $organization->id, 404);
    }

    private function ensureActorCanChangeRole(
        User $actor,
        Organization $organization,
        OrganizationMembership $membership,
        OrganizationRole $newRole,
    ): void {
        if ($actor->is_platform_admin) {
            return;
        }

        $actorRole = $organization->memberships()
            ->where('user_id', $actor->id)
            ->first()
            ?->role;

        if ($actorRole !== OrganizationRole::Owner
            && ($membership->role === OrganizationRole::Owner || $newRole === OrganizationRole::Owner)) {
            throw new AuthorizationException('Only an organization owner may manage owner access.');
        }
    }

    private function ensureAnotherOwnerExists(Organization $organization): void
    {
        if ($organization->memberships()->where('role', OrganizationRole::Owner->value)->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Assign another owner before changing or removing the final owner.',
            ]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        $suffix = 2;

        while (Organization::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
