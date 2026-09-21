<?php

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationInvitationService
{
    public function preview(string $plainToken): OrganizationInvitation
    {
        return $this->pendingInvitation($plainToken)->load('organization');
    }

    public function invite(
        User $actor,
        Organization $organization,
        string $email,
        OrganizationRole $role,
    ): OrganizationInvitation {
        $email = Str::lower($email);
        $this->ensureActorMayAssignRole($actor, $organization, $role);

        if ($organization->users()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'This person is already a member.']);
        }

        $plainToken = Str::random(64);

        $invitation = DB::transaction(function () use ($actor, $organization, $email, $role, $plainToken): OrganizationInvitation {
            $invitation = $organization->invitations()
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->first() ?? new OrganizationInvitation(['organization_id' => $organization->id]);

            $invitation->fill([
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $plainToken),
                'invited_by' => $actor->id,
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
            ])->save();

            return $invitation;
        });

        Notification::route('mail', $email)->notify(
            new OrganizationInvitationNotification($organization, $role, $plainToken),
        );

        return $invitation->load('inviter');
    }

    public function accept(User $user, string $plainToken): Organization
    {
        $invitation = $this->pendingInvitation($plainToken)->load('organization');

        if (Str::lower($user->email) !== Str::lower($invitation->email)) {
            throw new AuthorizationException('Sign in with the email address that received this invitation.');
        }

        return DB::transaction(function () use ($user, $invitation): Organization {
            $invitation->organization->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $invitation->role, 'joined_at' => now()],
            );

            $invitation->update(['accepted_at' => now()]);

            return $invitation->organization;
        });
    }

    public function registerAndAccept(array $attributes, string $plainToken): User
    {
        $invitation = $this->pendingInvitation($plainToken)->load('organization');

        if (User::where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'An account already exists for this email. Sign in to accept the invitation.',
            ]);
        }

        return DB::transaction(function () use ($attributes, $invitation): User {
            $user = User::create([
                'name' => $attributes['name'],
                'email' => $invitation->email,
                'password' => Hash::make($attributes['password']),
                'email_verified_at' => now(),
            ]);

            $invitation->organization->memberships()->create([
                'user_id' => $user->id,
                'role' => $invitation->role,
                'joined_at' => now(),
            ]);
            $invitation->update(['accepted_at' => now()]);

            return $user->load('memberships.organization');
        });
    }

    private function ensureActorMayAssignRole(
        User $actor,
        Organization $organization,
        OrganizationRole $role,
    ): void {
        if ($actor->is_platform_admin || $role !== OrganizationRole::Owner) {
            return;
        }

        $isOwner = $organization->memberships()
            ->where('user_id', $actor->id)
            ->where('role', OrganizationRole::Owner->value)
            ->exists();

        if (! $isOwner) {
            throw new AuthorizationException('Only an organization owner may invite another owner.');
        }
    }

    private function pendingInvitation(string $plainToken): OrganizationInvitation
    {
        return OrganizationInvitation::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();
    }
}
