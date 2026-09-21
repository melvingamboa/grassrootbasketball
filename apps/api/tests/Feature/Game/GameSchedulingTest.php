<?php

namespace Tests\Feature\Game;

use App\Enums\OrganizationRole;
use App\Models\Competition;
use App\Models\Division;
use App\Models\Game;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Season;
use App\Models\SeasonTeamRegistration;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_publish_a_game_and_viewers_can_read_the_schedule(): void
    {
        $pilot = $this->pilot();

        $game = $this->actingAs($pilot['owner'])->postJson($pilot['games_url'], [
            'division_id' => $pilot['division']->id,
            'home_team_registration_id' => $pilot['registrations'][0]->id,
            'away_team_registration_id' => $pilot['registrations'][1]->id,
            'venue_id' => $pilot['venue']->id,
            'scheduled_at' => '2026-10-03T18:00:00+08:00',
            'estimated_duration_minutes' => 90,
            'round' => 'Round 1',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.home_team.name', 'Lambakin')
            ->json('data');

        $this->assertDatabaseHas('games', ['id' => $game['id'], 'round' => 'Round 1']);
        $this->getJson($pilot['public_url'].'/games?date=2026-10-03')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.away_team.name', 'San Josef');
    }

    public function test_schedule_conflicts_warn_the_organizer_and_can_be_explicitly_overridden(): void
    {
        $pilot = $this->pilot();
        $this->actingAs($pilot['owner'])->postJson($pilot['games_url'], $this->gamePayload($pilot, 0, 1))->assertCreated();

        $this->postJson($pilot['games_url'], $this->gamePayload($pilot, 0, 2))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_at')
            ->assertJsonPath('errors.scheduled_at.0', 'A selected team overlaps game #1 (Lambakin vs San Josef).');

        $override = [...$this->gamePayload($pilot, 0, 2), 'allow_conflicts' => true];
        $this->postJson($pilot['games_url'], $override)->assertCreated();
        $this->assertDatabaseCount('games', 2);
    }

    public function test_game_lists_show_the_latest_scheduled_games_first(): void
    {
        $pilot = $this->pilot();
        $this->actingAs($pilot['owner']);
        $oldest = $this->postJson($pilot['games_url'], [
            ...$this->gamePayload($pilot, 0, 1),
            'scheduled_at' => '2026-10-01T18:00:00+08:00',
        ])->assertCreated()->json('data.id');
        $latest = $this->postJson($pilot['games_url'], [
            ...$this->gamePayload($pilot, 0, 1),
            'scheduled_at' => '2026-10-03T18:00:00+08:00',
        ])->assertCreated()->json('data.id');
        $middle = $this->postJson($pilot['games_url'], [
            ...$this->gamePayload($pilot, 0, 1),
            'scheduled_at' => '2026-10-02T18:00:00+08:00',
        ])->assertCreated()->json('data.id');

        $this->getJson($pilot['public_url'].'/games')
            ->assertOk()
            ->assertJsonPath('data.0.id', $latest)
            ->assertJsonPath('data.1.id', $middle)
            ->assertJsonPath('data.2.id', $oldest);
    }

    public function test_rescheduling_preserves_an_auditable_history(): void
    {
        $pilot = $this->pilot();
        $game = $this->actingAs($pilot['owner'])->postJson($pilot['games_url'], $this->gamePayload($pilot, 0, 1))->json('data');

        $this->patchJson("{$pilot['games_url']}/{$game['id']}", [
            'scheduled_at' => '2026-10-04T19:00:00+08:00',
            'change_reason' => 'Municipal gym maintenance finished one day later.',
        ])->assertOk()
            ->assertJsonPath('data.changes.0.change_type', 'schedule')
            ->assertJsonPath('data.changes.0.reason', 'Municipal gym maintenance finished one day later.');

        $this->assertDatabaseHas('game_changes', [
            'game_id' => $game['id'],
            'change_type' => 'schedule',
            'changed_by' => $pilot['owner']->id,
        ]);
    }

    public function test_disrupted_status_requires_a_public_reason_and_records_the_change(): void
    {
        $pilot = $this->pilot();
        $game = $this->actingAs($pilot['owner'])->postJson($pilot['games_url'], $this->gamePayload($pilot, 0, 1))->json('data');

        $this->patchJson("{$pilot['games_url']}/{$game['id']}", [
            'status' => 'postponed',
            'change_reason' => 'Status updated by the organizer.',
        ])->assertUnprocessable()->assertJsonValidationErrors('status_reason');

        $this->patchJson("{$pilot['games_url']}/{$game['id']}", [
            'status' => 'postponed',
            'status_reason' => 'Heavy rain made the court unsafe.',
            'change_reason' => 'Postponed after venue inspection.',
        ])->assertOk()->assertJsonPath('data.status', 'postponed');

        $this->assertDatabaseHas('game_changes', ['game_id' => $game['id'], 'from_status' => 'scheduled', 'to_status' => 'postponed']);
    }

    public function test_manual_final_result_requires_two_non_tied_scores(): void
    {
        $pilot = $this->pilot();
        $game = $this->actingAs($pilot['owner'])->postJson($pilot['games_url'], $this->gamePayload($pilot, 0, 1))->json('data');
        $url = "{$pilot['games_url']}/{$game['id']}";

        $this->patchJson($url, ['status' => 'final', 'home_score' => 80, 'change_reason' => 'Official score sheet.'])
            ->assertUnprocessable()->assertJsonValidationErrors('home_score');
        $this->patchJson($url, ['status' => 'final', 'home_score' => 80, 'away_score' => 80, 'change_reason' => 'Official score sheet.'])
            ->assertUnprocessable()->assertJsonValidationErrors('home_score');
        $this->patchJson($url, ['status' => 'final', 'home_score' => 82, 'away_score' => 76, 'change_reason' => 'Signed official score sheet.'])
            ->assertOk()->assertJsonPath('data.status', 'final')->assertJsonPath('data.home_score', 82);

        $this->assertDatabaseHas('games', ['id' => $game['id'], 'status' => 'final', 'home_score' => 82, 'away_score' => 76]);
    }

    public function test_public_competition_homepage_only_includes_published_announcements(): void
    {
        $pilot = $this->pilot();
        $announcementsUrl = str_replace('/games', '/announcements', $pilot['games_url']);

        $this->actingAs($pilot['owner'])->postJson($announcementsUrl, [
            'title' => 'Internal draft', 'body' => 'Not ready for viewers.', 'status' => 'draft',
        ])->assertCreated();
        $this->postJson($announcementsUrl, [
            'title' => 'Opening night', 'body' => 'Doors open at 5:00 PM.', 'status' => 'published',
        ])->assertCreated();

        $response = $this->getJson($pilot['public_url'])
            ->assertOk()
            ->assertJsonCount(1, 'data.announcements')
            ->assertJsonPath('data.announcements.0.title', 'Opening night');

        $this->assertStringNotContainsString('Internal draft', $response->getContent());
    }

    public function test_organizer_can_publish_safe_livestream_metadata_for_viewers(): void
    {
        $pilot = $this->pilot();
        $game = $this->actingAs($pilot['owner'])->postJson($pilot['games_url'], $this->gamePayload($pilot, 0, 1))->json('data');
        $url = "{$pilot['games_url']}/{$game['id']}";

        $this->patchJson($url, [
            'livestream_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
            'livestream_status' => 'live',
            'change_reason' => 'Official organizer livestream is now live.',
        ])->assertOk()
            ->assertJsonPath('data.livestream.provider', 'youtube')
            ->assertJsonPath('data.livestream.status', 'live')
            ->assertJsonPath('data.livestream.embed_url', 'https://www.youtube-nocookie.com/embed/abcdefghijk')
            ->assertJsonPath('data.changes.0.change_type', 'livestream');

        $this->getJson("{$pilot['public_url']}/games/{$game['id']}")
            ->assertOk()
            ->assertJsonPath('data.livestream.provider_label', 'YouTube')
            ->assertJsonPath('data.livestream.can_embed', true)
            ->assertJsonMissingPath('data.changes');

        $this->patchJson($url, [
            'livestream_url' => 'https://www.facebook.com/jaenleague/videos/123456789',
            'livestream_status' => 'scheduled',
            'change_reason' => 'Moved to the official Facebook coverage.',
        ])->assertOk()
            ->assertJsonPath('data.livestream.provider', 'facebook')
            ->assertJsonPath('data.livestream.embed_url', null)
            ->assertJsonPath('data.livestream.can_embed', false);

        $this->patchJson($url, [
            'livestream_status' => 'unavailable',
            'change_reason' => 'Temporarily hide the stream while the connection is checked.',
        ])->assertOk();
        $this->getJson("{$pilot['public_url']}/games/{$game['id']}")
            ->assertOk()
            ->assertJsonPath('data.livestream', null);
    }

    public function test_livestream_management_rejects_unsafe_urls_and_scorer_only_accounts(): void
    {
        $pilot = $this->pilot();
        $game = $this->actingAs($pilot['owner'])->postJson($pilot['games_url'], $this->gamePayload($pilot, 0, 1))->json('data');
        $url = "{$pilot['games_url']}/{$game['id']}";

        $this->patchJson($url, [
            'livestream_url' => 'http://youtube.com/watch?v=abcdefghijk',
            'livestream_status' => 'live',
            'change_reason' => 'Unsafe test URL.',
        ])->assertUnprocessable()->assertJsonValidationErrors('livestream_url');
        $this->patchJson($url, [
            'livestream_url' => 'https://youtube.com.example.test/watch?v=abcdefghijk',
            'livestream_status' => 'live',
            'change_reason' => 'Spoofed provider URL.',
        ])->assertUnprocessable()->assertJsonValidationErrors('livestream_url');

        $scorer = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $pilot['organization']->id,
            'user_id' => $scorer->id,
            'role' => OrganizationRole::Scorer,
            'joined_at' => now(),
        ]);
        $this->actingAs($scorer)->patchJson($url, [
            'livestream_url' => 'https://youtu.be/abcdefghijk',
            'livestream_status' => 'live',
            'change_reason' => 'Scorer should not manage broadcasts.',
        ])->assertForbidden();
    }

    public function test_scorer_and_cross_tenant_records_are_rejected(): void
    {
        $pilot = $this->pilot();
        $scorer = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $pilot['organization']->id,
            'user_id' => $scorer->id,
            'role' => OrganizationRole::Scorer,
            'joined_at' => now(),
        ]);
        $other = $this->pilot('Other League');
        $foreignGame = Game::create([
            'season_id' => $other['season']->id,
            'division_id' => $other['division']->id,
            'home_team_registration_id' => $other['registrations'][0]->id,
            'away_team_registration_id' => $other['registrations'][1]->id,
            'scheduled_at' => '2026-10-03 10:00:00',
            'estimated_duration_minutes' => 90,
            'status' => 'scheduled',
        ]);

        $this->actingAs($scorer)->postJson($pilot['games_url'], $this->gamePayload($pilot, 0, 1))->assertForbidden();
        $this->actingAs($pilot['owner'])->getJson("{$pilot['games_url']}/{$foreignGame->id}")->assertNotFound();
    }

    private function gamePayload(array $pilot, int $homeIndex, int $awayIndex): array
    {
        return [
            'division_id' => $pilot['division']->id,
            'home_team_registration_id' => $pilot['registrations'][$homeIndex]->id,
            'away_team_registration_id' => $pilot['registrations'][$awayIndex]->id,
            'venue_id' => $pilot['venue']->id,
            'scheduled_at' => '2026-10-03T18:00:00+08:00',
            'estimated_duration_minutes' => 90,
            'round' => 'Round 1',
        ];
    }

    private function pilot(string $name = 'Jaen Basketball'): array
    {
        $owner = User::factory()->create();
        $slug = str($name)->slug()->toString();
        $organization = Organization::create(['name' => $name, 'slug' => $slug, 'created_by' => $owner->id]);
        OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => OrganizationRole::Owner, 'joined_at' => now()]);
        $competition = Competition::create(['organization_id' => $organization->id, 'name' => 'Pilot League', 'slug' => 'pilot-league', 'type' => 'league', 'status' => 'active']);
        $season = Season::create(['competition_id' => $competition->id, 'name' => '2026', 'slug' => '2026', 'timezone' => 'Asia/Manila', 'format' => 'round_robin', 'status' => 'scheduled', 'max_roster_size' => 20, 'period_count' => 4, 'period_minutes' => 10, 'overtime_minutes' => 5]);
        $division = Division::create(['season_id' => $season->id, 'name' => 'Open Division', 'slug' => 'open-division', 'category' => 'open', 'gender' => 'open', 'is_active' => true]);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'Municipal Gym', 'slug' => 'municipal-gym', 'status' => 'active']);
        $registrations = collect(['Lambakin', 'San Josef', 'Niyugan'])->map(function (string $teamName) use ($organization, $season, $division): SeasonTeamRegistration {
            $team = Team::create(['organization_id' => $organization->id, 'name' => $teamName, 'slug' => str($teamName)->slug(), 'primary_color' => '#f59e0b', 'secondary_color' => '#0f172a', 'status' => 'active']);

            return SeasonTeamRegistration::create(['season_id' => $season->id, 'division_id' => $division->id, 'team_id' => $team->id, 'status' => 'approved']);
        })->all();
        $path = "/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons/{$season->slug}";

        return [
            'owner' => $owner,
            'organization' => $organization,
            'competition' => $competition,
            'season' => $season,
            'division' => $division,
            'venue' => $venue,
            'registrations' => $registrations,
            'games_url' => '/api/v1'.$path.'/games',
            'public_url' => '/api/v1/public'.$path,
        ];
    }
}
