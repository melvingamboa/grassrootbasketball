<?php

namespace Tests\Feature\Competition;

use App\Enums\OrganizationRole;
use App\Models\AdministrativeArea;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_build_only_a_valid_administrative_hierarchy(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $province = $this->actingAs($admin)->postJson('/api/v1/administrative-areas', [
            'type' => 'province', 'name' => 'Nueva Ecija',
        ])->assertCreated()->json('data');

        $this->postJson('/api/v1/administrative-areas', [
            'parent_id' => $province['id'], 'type' => 'municipality', 'name' => 'Jaen',
        ])->assertCreated();

        $this->postJson('/api/v1/administrative-areas', [
            'parent_id' => $province['id'], 'type' => 'barangay', 'name' => 'Invalid Barangay',
        ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');

        $this->getJson('/api/v1/administrative-areas')
            ->assertOk()
            ->assertJsonPath('data.1.path', 'Nueva Ecija / Jaen');
    }

    public function test_organizer_can_configure_the_complete_pilot_season(): void
    {
        [$organization, $owner] = $this->organizationWithRole(OrganizationRole::Owner);
        $area = AdministrativeArea::create(['type' => 'province', 'name' => 'Nueva Ecija']);

        $venue = $this->actingAs($owner)->postJson("/api/v1/organizations/{$organization->slug}/venues", [
            'name' => 'Jaen Municipal Gym', 'administrative_area_id' => $area->id,
            'address' => 'Jaen, Nueva Ecija', 'latitude' => 15.328, 'longitude' => 120.919,
            'status' => 'active',
        ])->assertCreated()->json('data');

        $competition = $this->postJson("/api/v1/organizations/{$organization->slug}/competitions", [
            'name' => 'Inter-Purok Basketball', 'administrative_area_id' => $area->id,
            'type' => 'league', 'status' => 'draft', 'description' => 'Pilot competition',
        ])->assertCreated()->json('data');

        $season = $this->postJson("/api/v1/organizations/{$organization->slug}/competitions/{$competition['slug']}/seasons", [
            'name' => '2026 Season', 'primary_venue_id' => $venue['id'],
            'starts_on' => '2026-10-01', 'ends_on' => '2026-12-20', 'timezone' => 'Asia/Manila',
            'format' => 'round_robin', 'status' => 'registration', 'max_roster_size' => 20,
            'period_count' => 4, 'period_minutes' => 10, 'overtime_minutes' => 5,
            'rules_notes' => 'Top four advance to single-game playoffs.',
        ])->assertCreated()->assertJsonCount(1, 'data.divisions')->json('data');

        $this->postJson("/api/v1/organizations/{$organization->slug}/competitions/{$competition['slug']}/seasons/{$season['slug']}/divisions", [
            'name' => 'Juniors U18', 'category' => 'juniors', 'gender' => 'male',
            'minimum_age' => 14, 'maximum_age' => 18, 'is_active' => true,
        ])->assertCreated();

        $this->patchJson("/api/v1/organizations/{$organization->slug}/competitions/{$competition['slug']}", [
            'status' => 'active',
        ])->assertOk()->assertJsonPath('data.status', 'active');

        $this->patchJson("/api/v1/organizations/{$organization->slug}/competitions/{$competition['slug']}/seasons/{$season['slug']}", [
            'status' => 'scheduled',
        ])->assertOk()->assertJsonPath('data.status', 'scheduled');

        $this->getJson("/api/v1/organizations/{$organization->slug}/competitions/{$competition['slug']}")
            ->assertOk()->assertJsonCount(2, 'data.seasons.0.divisions');
    }

    public function test_roster_limit_cannot_exceed_the_approved_twenty_players(): void
    {
        [$organization, $owner] = $this->organizationWithRole(OrganizationRole::Owner);
        $competition = $organization->competitions()->create([
            'name' => 'Pilot', 'slug' => 'pilot', 'type' => 'league', 'status' => 'draft',
        ]);

        $this->actingAs($owner)->postJson("/api/v1/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons", [
            'name' => 'Invalid Season', 'timezone' => 'Asia/Manila', 'format' => 'round_robin',
            'status' => 'registration', 'max_roster_size' => 21, 'period_count' => 4,
            'period_minutes' => 10, 'overtime_minutes' => 5,
        ])->assertUnprocessable()->assertJsonValidationErrors('max_roster_size');
    }

    public function test_league_manager_can_manage_competitions_but_scorer_and_outsider_cannot(): void
    {
        [$organization, $manager] = $this->organizationWithRole(OrganizationRole::LeagueManager);
        $scorer = User::factory()->create();
        OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $scorer->id, 'role' => OrganizationRole::Scorer, 'joined_at' => now()]);
        $outsider = User::factory()->create();

        $this->actingAs($manager)->postJson("/api/v1/organizations/{$organization->slug}/competitions", [
            'name' => 'Manager League', 'type' => 'league', 'status' => 'draft',
        ])->assertCreated();
        $this->actingAs($scorer)->postJson("/api/v1/organizations/{$organization->slug}/competitions", [
            'name' => 'Scorer League', 'type' => 'league', 'status' => 'draft',
        ])->assertForbidden();
        $this->actingAs($outsider)->getJson("/api/v1/organizations/{$organization->slug}/competitions")
            ->assertForbidden();
    }

    public function test_a_season_cannot_reference_another_organizations_venue(): void
    {
        [$organization, $owner] = $this->organizationWithRole(OrganizationRole::Owner);
        [$otherOrganization] = $this->organizationWithRole(OrganizationRole::Owner, 'Other Office');
        $foreignVenue = Venue::create(['organization_id' => $otherOrganization->id, 'name' => 'Foreign Gym', 'slug' => 'foreign-gym', 'status' => 'active']);
        $competition = $organization->competitions()->create(['name' => 'Pilot', 'slug' => 'pilot', 'type' => 'league', 'status' => 'draft']);

        $this->actingAs($owner)->postJson("/api/v1/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons", [
            'name' => '2026', 'primary_venue_id' => $foreignVenue->id, 'timezone' => 'Asia/Manila',
            'format' => 'round_robin', 'status' => 'registration', 'max_roster_size' => 20,
            'period_count' => 4, 'period_minutes' => 10, 'overtime_minutes' => 5,
        ])->assertUnprocessable()->assertJsonValidationErrors('primary_venue_id');
    }

    private function organizationWithRole(OrganizationRole $role, string $name = 'Test League Office'): array
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => $name, 'slug' => str($name)->slug(), 'created_by' => $user->id]);
        OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => $role, 'joined_at' => now()]);

        return [$organization, $user];
    }
}
