<?php

namespace Tests\Feature\Team;

use App\Enums\OrganizationRole;
use App\Models\Competition;
use App\Models\Division;
use App\Models\Game;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Player;
use App\Models\Season;
use App\Models\SeasonTeamRegistration;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamAndRosterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_register_a_team_and_build_an_approved_roster(): void
    {
        [$organization, $owner, $competition, $season, $division] = $this->pilot();
        $team = $this->actingAs($owner)->postJson("/api/v1/organizations/{$organization->slug}/teams", [
            'name' => 'Lambakin Ballers', 'short_name' => 'LMB', 'primary_color' => '#f59e0b',
            'secondary_color' => '#0f172a', 'status' => 'active',
        ])->assertCreated()->json('data');
        $player = $this->postJson("/api/v1/organizations/{$organization->slug}/players", [
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'date_of_birth' => '2000-01-10',
            'contact_email' => 'private@example.test', 'contact_phone' => '09171234567', 'is_active' => true,
        ])->assertCreated()->json('data');
        $registration = $this->postJson("/api/v1/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons/{$season->slug}/team-registrations", [
            'team_id' => $team['id'], 'division_id' => $division->id, 'status' => 'approved',
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons/{$season->slug}/team-registrations/{$registration['id']}/players", [
            'player_id' => $player['id'], 'jersey_number' => 23, 'position' => 'guard', 'status' => 'approved',
        ])->assertCreated();

        $this->assertDatabaseHas('player_registrations', ['player_id' => $player['id'], 'jersey_number' => 23, 'status' => 'approved']);
    }

    public function test_organizer_can_create_a_player_directly_inside_the_selected_team_roster(): void
    {
        [$organization, $owner, $competition, $season, $division] = $this->pilot();
        $registration = $this->teamRegistration($organization, $season, $division);
        $url = "/api/v1/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons/{$season->slug}/team-registrations/{$registration->id}/players/create";

        $this->actingAs($owner)->postJson($url, [
            'first_name' => 'Miguel', 'middle_name' => 'Santos', 'last_name' => 'Garcia',
            'date_of_birth' => '2001-05-20', 'contact_phone' => '09170000000',
            'jersey_number' => 12, 'position' => 'center', 'status' => 'approved',
        ])->assertCreated()
            ->assertJsonPath('data.player.display_name', 'Miguel Santos Garcia')
            ->assertJsonPath('data.jersey_number', 12);

        $player = Player::where('first_name', 'Miguel')->firstOrFail();
        $this->assertDatabaseHas('player_registrations', [
            'season_team_registration_id' => $registration->id,
            'player_id' => $player->id,
            'jersey_number' => 12,
        ]);
    }

    public function test_quick_add_is_atomic_and_does_not_leave_an_orphan_player_when_roster_validation_fails(): void
    {
        [$organization, $owner, $competition, $season, $division] = $this->pilot();
        $registration = $this->teamRegistration($organization, $season, $division);
        $existing = Player::create(['organization_id' => $organization->id, 'first_name' => 'Existing', 'last_name' => 'Player']);
        $registration->playerRegistrations()->create(['player_id' => $existing->id, 'jersey_number' => 12, 'position' => 'guard', 'status' => 'approved']);

        $this->actingAs($owner)->postJson("/api/v1/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons/{$season->slug}/team-registrations/{$registration->id}/players/create", [
            'first_name' => 'Should', 'last_name' => 'Rollback', 'jersey_number' => 12,
            'position' => 'forward', 'status' => 'approved',
        ])->assertUnprocessable()->assertJsonValidationErrors('jersey_number');

        $this->assertDatabaseMissing('players', ['first_name' => 'Should', 'last_name' => 'Rollback']);
    }

    public function test_duplicate_players_and_jersey_numbers_are_rejected(): void
    {
        [$organization, $owner, $competition, $season, $division] = $this->pilot();
        $registration = $this->teamRegistration($organization, $season, $division);
        $first = Player::create(['organization_id' => $organization->id, 'first_name' => 'Juan', 'last_name' => 'One']);
        $second = Player::create(['organization_id' => $organization->id, 'first_name' => 'Pedro', 'last_name' => 'Two']);
        $url = "/api/v1/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons/{$season->slug}/team-registrations/{$registration->id}/players";

        $this->actingAs($owner)->postJson($url, ['player_id' => $first->id, 'jersey_number' => 7, 'position' => 'guard', 'status' => 'approved'])->assertCreated();
        $this->postJson($url, ['player_id' => $first->id, 'jersey_number' => 8, 'position' => 'guard', 'status' => 'approved'])->assertUnprocessable()->assertJsonValidationErrors('player_id');
        $this->postJson($url, ['player_id' => $second->id, 'jersey_number' => 7, 'position' => 'forward', 'status' => 'approved'])->assertUnprocessable()->assertJsonValidationErrors('jersey_number');
    }

    public function test_season_roster_limit_is_enforced(): void
    {
        [$organization, $owner, $competition, $season, $division] = $this->pilot();
        $season->update(['max_roster_size' => 1]);
        $registration = $this->teamRegistration($organization, $season, $division);
        $first = Player::create(['organization_id' => $organization->id, 'first_name' => 'First', 'last_name' => 'Player']);
        $second = Player::create(['organization_id' => $organization->id, 'first_name' => 'Second', 'last_name' => 'Player']);
        $url = "/api/v1/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons/{$season->slug}/team-registrations/{$registration->id}/players";

        $this->actingAs($owner)->postJson($url, ['player_id' => $first->id, 'jersey_number' => 1, 'position' => 'guard', 'status' => 'approved'])->assertCreated();
        $this->postJson($url, ['player_id' => $second->id, 'jersey_number' => 2, 'position' => 'forward', 'status' => 'approved'])->assertUnprocessable()->assertJsonValidationErrors('player_id');
    }

    public function test_organizer_can_remove_a_team_from_a_season_without_deleting_reusable_records(): void
    {
        [$organization, $owner, $competition, $season, $division] = $this->pilot();
        $registration = $this->teamRegistration($organization, $season, $division);
        $player = Player::create(['organization_id' => $organization->id, 'first_name' => 'Reusable', 'last_name' => 'Player']);
        $playerRegistration = $registration->playerRegistrations()->create([
            'player_id' => $player->id, 'jersey_number' => 8, 'position' => 'guard', 'status' => 'approved',
        ]);
        $url = "/api/v1/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons/{$season->slug}/team-registrations/{$registration->id}";

        $this->actingAs($owner)->deleteJson($url)->assertNoContent();

        $this->assertDatabaseMissing('season_team_registrations', ['id' => $registration->id]);
        $this->assertDatabaseMissing('player_registrations', ['id' => $playerRegistration->id]);
        $this->assertDatabaseHas('teams', ['id' => $registration->team_id]);
        $this->assertDatabaseHas('players', ['id' => $player->id]);
    }

    public function test_team_with_a_scheduled_game_must_be_withdrawn_instead_of_removed(): void
    {
        [$organization, $owner, $competition, $season, $division] = $this->pilot();
        $home = $this->teamRegistration($organization, $season, $division);
        $awayTeam = Team::create(['organization_id' => $organization->id, 'name' => 'San Josef', 'slug' => 'san-josef', 'primary_color' => '#f59e0b', 'secondary_color' => '#0f172a', 'status' => 'active']);
        $away = SeasonTeamRegistration::create(['season_id' => $season->id, 'division_id' => $division->id, 'team_id' => $awayTeam->id, 'status' => 'approved']);
        Game::create([
            'season_id' => $season->id,
            'division_id' => $division->id,
            'home_team_registration_id' => $home->id,
            'away_team_registration_id' => $away->id,
            'scheduled_at' => '2026-10-03 18:00:00',
            'estimated_duration_minutes' => 90,
            'status' => 'scheduled',
        ]);
        $url = "/api/v1/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons/{$season->slug}/team-registrations/{$home->id}";

        $this->actingAs($owner)->deleteJson($url)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_registration')
            ->assertJsonPath('errors.team_registration.0', 'This team cannot be removed because it already has scheduled or completed games. Change its registration status to Withdrawn instead.');

        $this->assertDatabaseHas('season_team_registrations', ['id' => $home->id]);
    }

    public function test_scorer_cannot_remove_a_team_from_a_season(): void
    {
        [$organization, $owner, $competition, $season, $division] = $this->pilot();
        $registration = $this->teamRegistration($organization, $season, $division);
        $scorer = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $scorer->id,
            'role' => OrganizationRole::Scorer,
            'joined_at' => now(),
        ]);
        $url = "/api/v1/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons/{$season->slug}/team-registrations/{$registration->id}";

        $this->actingAs($scorer)->deleteJson($url)->assertForbidden();

        $this->assertDatabaseHas('season_team_registrations', ['id' => $registration->id]);
    }

    public function test_public_team_page_only_exposes_approved_safe_roster_fields(): void
    {
        [$organization, $owner, $competition, $season, $division] = $this->pilot();
        $registration = $this->teamRegistration($organization, $season, $division);
        $approved = Player::create(['organization_id' => $organization->id, 'first_name' => 'Public', 'last_name' => 'Player', 'date_of_birth' => '1999-01-01', 'contact_email' => 'secret@example.test', 'contact_phone' => 'secret']);
        $pending = Player::create(['organization_id' => $organization->id, 'first_name' => 'Pending', 'last_name' => 'Player']);
        $registration->playerRegistrations()->create(['player_id' => $approved->id, 'jersey_number' => 10, 'position' => 'guard', 'status' => 'approved']);
        $registration->playerRegistrations()->create(['player_id' => $pending->id, 'jersey_number' => 11, 'position' => 'forward', 'status' => 'pending']);

        $response = $this->actingAs($owner)->getJson("/api/v1/public/organizations/{$organization->slug}/teams/{$registration->team->slug}")
            ->assertOk()->assertJsonCount(1, 'data.participations.0.roster')
            ->assertJsonPath('data.participations.0.roster.0.name', 'Public Player');

        $this->assertStringNotContainsString('secret@example.test', $response->getContent());
        $this->assertStringNotContainsString('date_of_birth', $response->getContent());
        $this->assertStringNotContainsString('contact_phone', $response->getContent());
    }

    public function test_validated_team_and_player_images_are_stored_on_the_media_disk(): void
    {
        Storage::fake('public');
        config(['media.disk' => 'public']);
        [$organization, $owner] = $this->pilot();
        $team = Team::create(['organization_id' => $organization->id, 'name' => 'Image Team', 'slug' => 'image-team', 'primary_color' => '#f59e0b', 'secondary_color' => '#0f172a', 'status' => 'active']);
        $player = Player::create(['organization_id' => $organization->id, 'first_name' => 'Image', 'last_name' => 'Player']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->actingAs($owner)->postJson("/api/v1/organizations/{$organization->slug}/teams/{$team->slug}/logo", [
            'image' => UploadedFile::fake()->createWithContent('logo.png', $png),
        ])->assertOk();
        $this->postJson("/api/v1/organizations/{$organization->slug}/players/{$player->id}/photo", [
            'image' => UploadedFile::fake()->createWithContent('player.png', $png),
        ])->assertOk();

        Storage::disk('public')->assertExists($team->fresh()->logo_path);
        Storage::disk('public')->assertExists($player->fresh()->photo_path);
    }

    public function test_organization_boundaries_protect_teams_players_and_rosters(): void
    {
        [$organization, $owner, $competition, $season, $division] = $this->pilot();
        [$otherOrganization, $otherOwner] = $this->pilot('Other Organization');
        $foreignTeam = Team::create(['organization_id' => $otherOrganization->id, 'name' => 'Foreign Team', 'slug' => 'foreign-team', 'primary_color' => '#f59e0b', 'secondary_color' => '#0f172a', 'status' => 'active']);

        $this->actingAs($owner)->patchJson("/api/v1/organizations/{$organization->slug}/teams/{$foreignTeam->slug}", ['status' => 'inactive'])->assertNotFound();
        $this->actingAs($otherOwner)->getJson("/api/v1/organizations/{$organization->slug}/players")->assertForbidden();
        $this->actingAs($owner)->postJson("/api/v1/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons/{$season->slug}/team-registrations", [
            'team_id' => $foreignTeam->id, 'division_id' => $division->id, 'status' => 'approved',
        ])->assertNotFound();
    }

    private function pilot(string $organizationName = 'Jaen Basketball'): array
    {
        $owner = User::factory()->create();
        $slug = str($organizationName)->slug()->toString();
        $organization = Organization::create(['name' => $organizationName, 'slug' => $slug, 'created_by' => $owner->id]);
        OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => OrganizationRole::Owner, 'joined_at' => now()]);
        $competition = Competition::create(['organization_id' => $organization->id, 'name' => 'Pilot League', 'slug' => 'pilot-league', 'type' => 'league', 'status' => 'active']);
        $season = Season::create(['competition_id' => $competition->id, 'name' => '2026', 'slug' => '2026', 'timezone' => 'Asia/Manila', 'format' => 'round_robin', 'status' => 'registration', 'max_roster_size' => 20, 'period_count' => 4, 'period_minutes' => 10, 'overtime_minutes' => 5]);
        $division = Division::create(['season_id' => $season->id, 'name' => 'Open Division', 'slug' => 'open-division', 'category' => 'open', 'gender' => 'open', 'is_active' => true]);

        return [$organization, $owner, $competition, $season, $division];
    }

    private function teamRegistration(Organization $organization, Season $season, Division $division): SeasonTeamRegistration
    {
        $team = Team::create(['organization_id' => $organization->id, 'name' => 'Lambakin', 'slug' => 'lambakin', 'primary_color' => '#f59e0b', 'secondary_color' => '#0f172a', 'status' => 'active']);

        return SeasonTeamRegistration::create(['season_id' => $season->id, 'division_id' => $division->id, 'team_id' => $team->id, 'status' => 'approved']);
    }
}
