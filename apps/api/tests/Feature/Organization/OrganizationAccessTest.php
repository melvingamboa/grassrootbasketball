<?php

namespace Tests\Feature\Organization;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_platform_administrator_can_create_an_organization_as_its_owner(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true]);

        $response = $this->actingAs($user)->postJson('/api/v1/organizations', [
            'name' => 'Jaen Summer League',
            'description' => 'A municipality-wide organizer account.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'jaen-summer-league')
            ->assertJsonPath('data.role', 'owner');

        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $response->json('data.id'),
            'user_id' => $user->id,
            'role' => OrganizationRole::Owner->value,
        ]);
    }

    public function test_an_ordinary_verified_user_cannot_create_an_organization(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/organizations', [
            'name' => 'Unauthorized League Office',
        ])->assertForbidden();
    }

    public function test_an_outsider_cannot_view_an_organization(): void
    {
        [$organization] = $this->organizationWithOwner();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->getJson("/api/v1/organizations/{$organization->slug}")
            ->assertForbidden();
    }

    public function test_an_owner_can_invite_a_member_and_an_invited_user_can_accept(): void
    {
        Notification::fake();
        [$organization, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner)
            ->postJson("/api/v1/organizations/{$organization->slug}/invitations", [
                'email' => 'scorer@example.test',
                'role' => OrganizationRole::Scorer->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'scorer');

        Notification::assertSentOnDemand(OrganizationInvitationNotification::class);

        $plainToken = str_repeat('a', 64);
        OrganizationInvitation::query()->update([
            'token_hash' => hash('sha256', $plainToken),
        ]);
        $scorer = User::factory()->create(['email' => 'scorer@example.test']);

        $this->actingAs($scorer)
            ->postJson('/api/v1/invitations/accept', ['token' => $plainToken])
            ->assertOk()
            ->assertJsonPath('data.role', 'scorer');

        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $scorer->id,
            'role' => OrganizationRole::Scorer->value,
        ]);
    }

    public function test_an_invited_person_can_preview_the_invitation_and_activate_an_account(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $plainToken = str_repeat('b', 64);
        OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'email' => 'newstaff@example.test',
            'role' => OrganizationRole::Scorer,
            'token_hash' => hash('sha256', $plainToken),
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->getJson("/api/v1/invitations/{$plainToken}")
            ->assertOk()
            ->assertJsonPath('data.email', 'newstaff@example.test')
            ->assertJsonPath('data.organization.slug', $organization->slug);

        $this->withHeader('Origin', 'http://localhost:5175')
            ->postJson("/api/v1/invitations/{$plainToken}/register", [
                'name' => 'New Staff Member',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'newstaff@example.test');

        $user = User::where('email', 'newstaff@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::Scorer->value,
        ]);
        $this->assertNotNull(OrganizationInvitation::firstOrFail()->accepted_at);
    }

    public function test_an_organization_cannot_lose_its_final_owner(): void
    {
        [$organization, $owner, $membership] = $this->organizationWithOwner();

        $this->actingAs($owner)
            ->deleteJson("/api/v1/organizations/{$organization->slug}/members/{$membership->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_an_administrator_cannot_grant_or_remove_owner_access(): void
    {
        [$organization, $owner, $ownerMembership] = $this->organizationWithOwner();
        $admin = User::factory()->create();
        $adminMembership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'role' => OrganizationRole::Admin,
            'joined_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/organizations/{$organization->slug}/members/{$adminMembership->id}", [
                'role' => OrganizationRole::Owner->value,
            ])->assertForbidden();

        $this->actingAs($admin)
            ->deleteJson("/api/v1/organizations/{$organization->slug}/members/{$ownerMembership->id}")
            ->assertForbidden();
    }

    private function organizationWithOwner(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Test League Office',
            'slug' => 'test-league-office',
            'created_by' => $owner->id,
        ]);
        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => OrganizationRole::Owner,
            'joined_at' => now(),
        ]);

        return [$organization, $owner, $membership];
    }
}
