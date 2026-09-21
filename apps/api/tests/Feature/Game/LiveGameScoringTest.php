<?php

namespace Tests\Feature\Game;

use App\Enums\OrganizationRole;
use App\Events\GameLiveUpdated;
use App\Models\Competition;
use App\Models\Division;
use App\Models\Game;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Season;
use App\Models\SeasonTeamRegistration;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LiveGameScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_scorer_can_start_a_game_and_record_an_audited_score(): void
    {
        Event::fake([GameLiveUpdated::class]);
        $pilot = $this->pilot();

        $this->actingAs($pilot['scorer'])->postJson($pilot['live_url'], ['action' => 'start'])
            ->assertOk()->assertJsonPath('data.status', 'live')->assertJsonPath('data.clock_seconds_remaining', 600);
        $this->postJson($pilot['live_url'], ['action' => 'score', 'team' => 'home', 'points' => 3])
            ->assertOk()->assertJsonPath('data.home_score', 3)
            ->assertJsonPath('data.period_scores.0.home', 3)
            ->assertJsonPath('data.live_events.0.event_type', 'score');

        $this->assertDatabaseHas('game_live_events', ['game_id' => $pilot['game']->id, 'points_delta' => 3, 'recorded_by' => $pilot['scorer']->id]);
        Event::assertDispatched(GameLiveUpdated::class, 2);
    }

    public function test_correction_requires_a_reason_and_cannot_make_a_negative_score(): void
    {
        Event::fake([GameLiveUpdated::class]);
        $pilot = $this->pilot();
        $this->actingAs($pilot['scorer'])->postJson($pilot['live_url'], ['action' => 'start']);

        $this->postJson($pilot['live_url'], ['action' => 'correct', 'team' => 'home', 'points' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($pilot['live_url'], ['action' => 'correct', 'team' => 'home', 'points' => 1, 'reason' => 'Wrong team'])
            ->assertUnprocessable()->assertJsonValidationErrors('points');
        $this->postJson($pilot['live_url'], ['action' => 'score', 'team' => 'away', 'points' => 2])->assertOk();
        $this->postJson($pilot['live_url'], ['action' => 'correct', 'team' => 'away', 'points' => 1, 'reason' => 'Official table correction'])
            ->assertOk()->assertJsonPath('data.away_score', 1);
    }

    public function test_clock_and_period_controls_use_the_season_rules(): void
    {
        Event::fake([GameLiveUpdated::class]);
        CarbonImmutable::setTestNow('2026-10-03 10:00:00');
        $pilot = $this->pilot();
        $this->actingAs($pilot['scorer'])->postJson($pilot['live_url'], ['action' => 'start']);
        $this->postJson($pilot['live_url'], ['action' => 'clock_start'])->assertJsonPath('data.clock_running', true);

        CarbonImmutable::setTestNow('2026-10-03 10:00:12');
        $this->postJson($pilot['live_url'], ['action' => 'clock_pause'])
            ->assertOk()->assertJsonPath('data.clock_seconds_remaining', 588)->assertJsonPath('data.clock_running', false);
        $this->postJson($pilot['live_url'], ['action' => 'next_period'])
            ->assertOk()->assertJsonPath('data.current_period', 2)->assertJsonPath('data.clock_seconds_remaining', 600);
    }

    public function test_scorer_can_adjust_the_clock_with_an_audited_reason(): void
    {
        Event::fake([GameLiveUpdated::class]);
        CarbonImmutable::setTestNow('2026-10-03 10:00:00');
        $pilot = $this->pilot();
        $this->actingAs($pilot['scorer'])->postJson($pilot['live_url'], ['action' => 'start']);
        $this->postJson($pilot['live_url'], ['action' => 'clock_start']);

        CarbonImmutable::setTestNow('2026-10-03 10:00:05');
        $this->postJson($pilot['live_url'], ['action' => 'clock_adjust', 'clock_seconds' => 482])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($pilot['live_url'], [
            'action' => 'clock_adjust',
            'clock_seconds' => 482,
            'reason' => 'Matched the official scoreboard',
        ])->assertOk()
            ->assertJsonPath('data.clock_seconds_remaining', 482)
            ->assertJsonPath('data.clock_running', true)
            ->assertJsonPath('data.live_events.0.event_type', 'clock_adjustment')
            ->assertJsonPath('data.live_events.0.details.clock_seconds_before', 595)
            ->assertJsonPath('data.live_events.0.details.clock_seconds_after', 482);

        $this->assertDatabaseHas('game_live_events', ['game_id' => $pilot['game']->id, 'event_type' => 'clock_adjustment']);
        Event::assertDispatched(GameLiveUpdated::class, 3);
    }

    public function test_finalization_rejects_a_tie_and_recalculates_public_standings(): void
    {
        Event::fake([GameLiveUpdated::class]);
        $pilot = $this->pilot();
        $this->actingAs($pilot['scorer'])->postJson($pilot['live_url'], ['action' => 'start']);
        $this->postJson($pilot['live_url'], ['action' => 'finalize'])
            ->assertUnprocessable()->assertJsonValidationErrors('action');
        $this->postJson($pilot['live_url'], ['action' => 'score', 'team' => 'home', 'points' => 2]);
        $this->postJson($pilot['live_url'], ['action' => 'finalize'])
            ->assertOk()->assertJsonPath('data.status', 'final');

        $this->assertDatabaseHas('standings', ['season_id' => $pilot['season']->id, 'team_registration_id' => $pilot['registrations'][0]->id, 'wins' => 1, 'points_for' => 2]);
        $this->getJson($pilot['public_game_url'])
            ->assertOk()->assertJsonPath('data.status', 'final')->assertJsonPath('data.home_score', 2);
    }

    public function test_non_member_cannot_control_a_game(): void
    {
        Event::fake([GameLiveUpdated::class]);
        $pilot = $this->pilot();
        $this->actingAs(User::factory()->create())->postJson($pilot['live_url'], ['action' => 'start'])->assertForbidden();
    }

    private function pilot(): array
    {
        $owner = User::factory()->create();
        $scorer = User::factory()->create();
        $organization = Organization::create(['name' => 'Jaen Basketball', 'slug' => 'jaen-basketball', 'created_by' => $owner->id]);
        foreach ([[$owner, OrganizationRole::Owner], [$scorer, OrganizationRole::Scorer]] as [$user, $role]) {
            OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => $role, 'joined_at' => now()]);
        }
        $competition = Competition::create(['organization_id' => $organization->id, 'name' => 'Pilot League', 'slug' => 'pilot-league', 'type' => 'league', 'status' => 'active']);
        $season = Season::create(['competition_id' => $competition->id, 'name' => '2026', 'slug' => '2026', 'timezone' => 'Asia/Manila', 'format' => 'round_robin', 'status' => 'scheduled', 'max_roster_size' => 20, 'period_count' => 4, 'period_minutes' => 10, 'overtime_minutes' => 5]);
        $division = Division::create(['season_id' => $season->id, 'name' => 'Open', 'slug' => 'open', 'category' => 'open', 'gender' => 'open', 'is_active' => true]);
        $registrations = collect(['Lambakin', 'San Josef'])->map(function (string $name) use ($organization, $season, $division): SeasonTeamRegistration {
            $team = Team::create(['organization_id' => $organization->id, 'name' => $name, 'slug' => str($name)->slug(), 'primary_color' => '#f59e0b', 'secondary_color' => '#0f172a', 'status' => 'active']);

            return SeasonTeamRegistration::create(['season_id' => $season->id, 'division_id' => $division->id, 'team_id' => $team->id, 'status' => 'approved']);
        })->all();
        $game = Game::create(['season_id' => $season->id, 'division_id' => $division->id, 'home_team_registration_id' => $registrations[0]->id, 'away_team_registration_id' => $registrations[1]->id, 'scheduled_at' => '2026-10-03 10:00:00', 'estimated_duration_minutes' => 90, 'status' => 'scheduled']);
        $base = "/api/v1/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons/{$season->slug}/games/{$game->id}";

        return compact('owner', 'scorer', 'organization', 'competition', 'season', 'division', 'registrations', 'game') + [
            'live_url' => $base.'/live-actions',
            'public_game_url' => str_replace('/api/v1/organizations/', '/api/v1/public/organizations/', $base),
        ];
    }
}
