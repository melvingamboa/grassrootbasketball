<?php

namespace Tests\Feature\Game;

use App\Enums\OrganizationRole;
use App\Events\GameLiveUpdated;
use App\Models\Competition;
use App\Models\Division;
use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Season;
use App\Models\SeasonTeamRegistration;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GameBoxScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_scorer_can_select_an_approved_game_lineup(): void
    {
        Event::fake([GameLiveUpdated::class]);
        $pilot = $this->pilot();

        $this->actingAs($pilot['scorer'])->putJson($pilot['lineup_url'], [
            'home_player_registration_ids' => [$pilot['players'][0]->id, $pilot['players'][1]->id],
            'away_player_registration_ids' => [$pilot['players'][2]->id],
        ])->assertOk()->assertJsonCount(3, 'data.box_score');

        $this->assertDatabaseHas('game_player_stats', ['game_id' => $pilot['game']->id, 'player_registration_id' => $pilot['players'][0]->id]);
        Event::assertDispatched(GameLiveUpdated::class);
    }

    public function test_player_scoring_updates_the_team_score_and_audit_atomically(): void
    {
        Event::fake([GameLiveUpdated::class]);
        $pilot = $this->pilot();
        $this->actingAs($pilot['scorer'])->putJson($pilot['lineup_url'], [
            'home_player_registration_ids' => [$pilot['players'][0]->id],
            'away_player_registration_ids' => [$pilot['players'][2]->id],
        ]);
        $this->postJson($pilot['live_url'], ['action' => 'start']);

        $this->postJson($pilot['live_url'], [
            'action' => 'score', 'team' => 'home', 'points' => 3,
            'player_registration_id' => $pilot['players'][0]->id,
        ])->assertOk()
            ->assertJsonPath('data.home_score', 3)
            ->assertJsonPath('data.box_score.0.points', 3)
            ->assertJsonPath('data.unassigned_points.home', 0);

        $this->assertDatabaseHas('game_stat_events', ['game_id' => $pilot['game']->id, 'stat' => 'points', 'delta' => 3, 'value_after' => 3]);
        $this->postJson($pilot['live_url'], [
            'action' => 'correct', 'team' => 'home', 'points' => 1, 'reason' => 'Incorrect generic correction',
        ])->assertUnprocessable()->assertJsonValidationErrors('player_registration_id');
    }

    public function test_non_scoring_stats_and_corrections_are_audited(): void
    {
        Event::fake([GameLiveUpdated::class]);
        $pilot = $this->pilot();
        $box = $this->actingAs($pilot['scorer'])->putJson($pilot['lineup_url'], [
            'home_player_registration_ids' => [$pilot['players'][0]->id],
            'away_player_registration_ids' => [$pilot['players'][2]->id],
        ])->json('data.box_score');
        $this->postJson($pilot['live_url'], ['action' => 'start']);
        $url = $pilot['stats_url'].'/'.$box[0]['id'];

        $this->patchJson($url, ['stat' => 'rebounds', 'delta' => 1])
            ->assertOk()->assertJsonPath('data.box_score.0.rebounds', 1);
        $this->patchJson($url, ['stat' => 'rebounds', 'delta' => -1, 'reason' => 'Score table correction'])
            ->assertOk()
            ->assertJsonPath('data.box_score.0.rebounds', 0)
            ->assertJsonPath('data.live_events.0.event_type', 'player_stat')
            ->assertJsonPath('data.live_events.0.details.stat', 'rebounds')
            ->assertJsonPath('data.live_events.0.details.delta', -1);

        $this->assertDatabaseHas('game_stat_events', ['stat' => 'rebounds', 'delta' => -1, 'reason' => 'Score table correction']);
        $this->assertDatabaseHas('game_live_events', ['game_id' => $pilot['game']->id, 'event_type' => 'player_stat']);
    }

    public function test_team_only_points_are_reported_as_unassigned_in_the_internal_box_score(): void
    {
        Event::fake([GameLiveUpdated::class]);
        $pilot = $this->pilot();
        $this->actingAs($pilot['scorer'])->putJson($pilot['lineup_url'], [
            'home_player_registration_ids' => [$pilot['players'][0]->id],
            'away_player_registration_ids' => [$pilot['players'][2]->id],
        ]);
        $this->postJson($pilot['live_url'], ['action' => 'start']);
        $this->postJson($pilot['live_url'], ['action' => 'score', 'team' => 'home', 'points' => 2])
            ->assertOk()->assertJsonPath('data.unassigned_points.home', 2);
    }

    public function test_public_game_exposes_safe_player_box_scores(): void
    {
        Event::fake([GameLiveUpdated::class]);
        $pilot = $this->pilot();
        $this->actingAs($pilot['scorer'])->putJson($pilot['lineup_url'], [
            'home_player_registration_ids' => [$pilot['players'][0]->id],
            'away_player_registration_ids' => [$pilot['players'][2]->id],
        ]);
        $this->postJson($pilot['live_url'], ['action' => 'start']);
        $this->postJson($pilot['live_url'], ['action' => 'score', 'team' => 'home', 'points' => 2, 'player_registration_id' => $pilot['players'][0]->id]);

        $response = $this->getJson($pilot['public_url'])
            ->assertOk()->assertJsonPath('data.box_score.0.player_registration.player.display_name', 'Home Player 1')
            ->assertJsonPath('data.box_score.0.points', 2)
            ->assertJsonPath('data.live_events.0.event_type', 'score');
        $this->assertStringNotContainsString('contact_email', $response->getContent());
        $this->assertStringNotContainsString('recorded_by', $response->getContent());
    }

    public function test_lineup_rejects_a_player_from_the_wrong_team(): void
    {
        Event::fake([GameLiveUpdated::class]);
        $pilot = $this->pilot();

        $this->actingAs($pilot['scorer'])->putJson($pilot['lineup_url'], [
            'home_player_registration_ids' => [$pilot['players'][2]->id],
            'away_player_registration_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('home_player_registration_ids');
    }

    public function test_scorer_can_atomically_swap_an_on_court_player_with_a_bench_player(): void
    {
        Event::fake([GameLiveUpdated::class]);
        $pilot = $this->pilot();
        $this->actingAs($pilot['scorer'])->putJson($pilot['lineup_url'], [
            'home_player_registration_ids' => [$pilot['players'][0]->id, $pilot['players'][1]->id],
            'away_player_registration_ids' => [$pilot['players'][2]->id],
        ])->assertOk();
        GamePlayerStat::query()->where('game_id', $pilot['game']->id)
            ->where('player_registration_id', $pilot['players'][0]->id)
            ->update(['is_starter' => true, 'is_on_court' => true, 'court_slot' => 1]);
        $this->postJson($pilot['live_url'], ['action' => 'start'])->assertOk();

        $this->postJson(str_replace('/lineup', '/substitutions', $pilot['lineup_url']), [
            'team' => 'home',
            'player_out_registration_id' => $pilot['players'][0]->id,
            'player_in_registration_id' => $pilot['players'][1]->id,
        ])->assertOk()
            ->assertJsonPath('data.box_score.0.is_on_court', false)
            ->assertJsonPath('data.box_score.1.is_on_court', true)
            ->assertJsonPath('data.box_score.1.court_slot', 1);

        $this->assertDatabaseHas('game_live_events', ['game_id' => $pilot['game']->id, 'event_type' => 'substitution']);
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
        $teams = collect(['Home Team', 'Away Team'])->map(fn (string $name) => Team::create(['organization_id' => $organization->id, 'name' => $name, 'slug' => str($name)->slug(), 'primary_color' => '#f59e0b', 'secondary_color' => '#0f172a', 'status' => 'active']));
        $registrations = $teams->map(fn (Team $team) => SeasonTeamRegistration::create(['season_id' => $season->id, 'division_id' => $division->id, 'team_id' => $team->id, 'status' => 'approved']))->all();
        $players = collect([[0, 'Home', 'Player 1', 1], [0, 'Home', 'Player 2', 2], [1, 'Away', 'Player 1', 3], [1, 'Away', 'Player 2', 4]])->map(function (array $data) use ($organization, $registrations): PlayerRegistration {
            [$teamIndex, $firstName, $lastName, $jersey] = $data;
            $player = Player::create(['organization_id' => $organization->id, 'first_name' => $firstName, 'last_name' => $lastName, 'is_active' => true]);

            return PlayerRegistration::create(['season_team_registration_id' => $registrations[$teamIndex]->id, 'player_id' => $player->id, 'jersey_number' => $jersey, 'position' => 'guard', 'status' => 'approved']);
        })->all();
        $game = Game::create(['season_id' => $season->id, 'division_id' => $division->id, 'home_team_registration_id' => $registrations[0]->id, 'away_team_registration_id' => $registrations[1]->id, 'scheduled_at' => '2026-10-03 10:00:00', 'estimated_duration_minutes' => 90, 'status' => 'scheduled']);
        $base = "/api/v1/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons/{$season->slug}/games/{$game->id}";

        return compact('owner', 'scorer', 'organization', 'competition', 'season', 'division', 'registrations', 'players', 'game') + [
            'lineup_url' => $base.'/lineup',
            'live_url' => $base.'/live-actions',
            'stats_url' => $base.'/player-stats',
            'public_url' => str_replace('/api/v1/organizations/', '/api/v1/public/organizations/', $base),
        ];
    }
}
