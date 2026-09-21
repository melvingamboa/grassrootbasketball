<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandingsAndBracketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalculation_uses_only_final_games_and_orders_by_win_percentage_then_point_difference(): void
    {
        $pilot = $this->pilot();
        $this->game($pilot, 0, 1, 'final', 90, 80);
        $this->game($pilot, 2, 3, 'final', 100, 70);
        $this->game($pilot, 0, 2, 'final', 75, 80);
        $this->game($pilot, 1, 3, 'scheduled');

        $this->actingAs($pilot['owner'])->postJson($pilot['recalculate_url'], [
            'division_id' => $pilot['division']->id,
        ])->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.team.name', 'Niyugan')
            ->assertJsonPath('data.0.played', 2)
            ->assertJsonPath('data.0.wins', 2)
            ->assertJsonPath('data.0.points_for', 180)
            ->assertJsonPath('data.0.points_against', 145)
            ->assertJsonPath('data.0.point_difference', 35)
            ->assertJsonPath('data.2.team.name', 'San Josef')
            ->assertJsonPath('data.3.team.name', 'Dampulan');

        $this->getJson($pilot['public_url'])
            ->assertOk()
            ->assertJsonCount(4, 'data.standings')
            ->assertJsonPath('data.standings.1.rank', 2)
            ->assertJsonMissingPath('data.standings.0.team.contact_email');
    }

    public function test_result_finalization_and_correction_automatically_recalculate_the_table(): void
    {
        $pilot = $this->pilot();
        $game = $this->game($pilot, 0, 1, 'scheduled');
        $url = $pilot['games_url'].'/'.$game->id;

        $this->actingAs($pilot['owner'])->patchJson($url, [
            'status' => 'final',
            'home_score' => 88,
            'away_score' => 80,
            'change_reason' => 'Official result',
        ])->assertOk();
        $this->getJson($pilot['standings_url'])
            ->assertJsonPath('data.0.team.name', 'Lambakin')
            ->assertJsonPath('data.0.points_for', 88);

        $this->patchJson($url, [
            'home_score' => 79,
            'away_score' => 91,
            'change_reason' => 'Corrected official score',
        ])->assertOk();

        $this->getJson($pilot['standings_url'])
            ->assertJsonPath('data.0.team.name', 'San Josef')
            ->assertJsonPath('data.0.points_for', 91)
            ->assertJsonPath('data.1.team.name', 'Lambakin')
            ->assertJsonPath('data.1.points_against', 91);
    }

    public function test_equal_records_and_point_differences_are_ordered_by_points_scored(): void
    {
        $pilot = $this->pilot();
        $this->game($pilot, 0, 2, 'final', 80, 70);
        $this->game($pilot, 3, 0, 'final', 80, 70);
        $this->game($pilot, 1, 3, 'final', 90, 70);
        $this->game($pilot, 2, 1, 'final', 90, 70);

        $this->actingAs($pilot['owner'])->postJson($pilot['recalculate_url'], [
            'division_id' => $pilot['division']->id,
        ])->assertOk()
            ->assertJsonPath('data.0.team.name', 'Niyugan')
            ->assertJsonPath('data.1.team.name', 'San Josef')
            ->assertJsonPath('data.1.point_difference', 0)
            ->assertJsonPath('data.1.points_for', 160)
            ->assertJsonPath('data.2.team.name', 'Lambakin')
            ->assertJsonPath('data.2.point_difference', 0)
            ->assertJsonPath('data.2.points_for', 150);
    }

    public function test_qualification_metadata_survives_later_recalculation_and_foreign_teams_are_rejected(): void
    {
        $pilot = $this->pilot();
        $other = $this->pilot('Other League');
        $this->actingAs($pilot['owner'])->postJson($pilot['recalculate_url'], [
            'division_id' => $pilot['division']->id,
        ])->assertOk();

        $this->putJson($pilot['standings_url'], [
            'division_id' => $pilot['division']->id,
            'rows' => [
                $this->standingRow($pilot['registrations'][0], 'qualified', 'Top seed'),
            ],
        ])->assertOk()->assertJsonPath('data.1.qualification_status', 'qualified');

        $this->game($pilot, 1, 0, 'final', 95, 70);
        $this->postJson($pilot['recalculate_url'], ['division_id' => $pilot['division']->id])
            ->assertOk()
            ->assertJsonPath('data.0.team.name', 'San Josef')
            ->assertJsonPath('data.1.team.name', 'Lambakin')
            ->assertJsonPath('data.1.qualification_status', 'qualified')
            ->assertJsonPath('data.1.notes', 'Top seed');

        $this->putJson($pilot['standings_url'], [
            'division_id' => $pilot['division']->id,
            'rows' => [$this->standingRow($other['registrations'][0])],
        ])->assertUnprocessable()->assertJsonValidationErrors('rows.0.team_registration_id');

        $this->assertDatabaseCount('standings', 4);
    }

    public function test_league_manager_can_manage_standings_but_scorer_cannot(): void
    {
        $pilot = $this->pilot();
        $manager = $this->member($pilot['organization'], OrganizationRole::LeagueManager);
        $scorer = $this->member($pilot['organization'], OrganizationRole::Scorer);
        $payload = [
            'division_id' => $pilot['division']->id,
            'rows' => [$this->standingRow($pilot['registrations'][0])],
        ];

        $this->actingAs($manager)->putJson($pilot['standings_url'], $payload)->assertOk();
        $this->actingAs($scorer)->putJson($pilot['standings_url'], $payload)->assertForbidden();
        $this->actingAs($scorer)->postJson($pilot['recalculate_url'], ['division_id' => $pilot['division']->id])->assertForbidden();

        $this->assertDatabaseCount('standings', 4);
    }

    public function test_organizer_can_build_and_publish_a_manual_bracket(): void
    {
        $pilot = $this->pilot();
        $bracket = $this->actingAs($pilot['owner'])->postJson($pilot['brackets_url'], [
            'division_id' => $pilot['division']->id,
            'name' => 'Championship playoffs',
            'status' => 'draft',
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data');

        $matchUrl = "{$pilot['brackets_url']}/{$bracket['id']}/matches";
        $this->postJson($matchUrl, [
            'round_number' => 1,
            'match_number' => 1,
            'round_label' => 'Semifinal',
            'home_team_registration_id' => $pilot['registrations'][0]->id,
            'away_team_registration_id' => $pilot['registrations'][3]->id,
            'winner_team_registration_id' => $pilot['registrations'][0]->id,
        ])->assertCreated()->assertJsonPath('data.winner_team.name', 'Lambakin');

        $this->getJson($pilot['public_url'])->assertJsonCount(0, 'data.brackets');

        $this->patchJson("{$pilot['brackets_url']}/{$bracket['id']}", ['status' => 'published'])
            ->assertOk()->assertJsonPath('data.status', 'published');
        $this->getJson($pilot['public_url'])
            ->assertOk()
            ->assertJsonCount(1, 'data.brackets')
            ->assertJsonPath('data.brackets.0.matches.0.home_team.name', 'Lambakin')
            ->assertJsonPath('data.brackets.0.matches.0.away_team.name', 'Dampulan');
    }

    public function test_organizer_can_create_a_complete_eight_team_bracket_layout(): void
    {
        $pilot = $this->pilot();

        $this->actingAs($pilot['owner'])->postJson($pilot['brackets_url'], [
            'division_id' => $pilot['division']->id,
            'name' => 'Eight-team playoffs',
            'status' => 'draft',
            'template' => 'single_elimination_8',
        ])->assertCreated()
            ->assertJsonCount(7, 'data.matches')
            ->assertJsonPath('data.matches.0.round_label', 'Quarterfinals')
            ->assertJsonPath('data.matches.3.match_number', 4)
            ->assertJsonPath('data.matches.4.round_label', 'Semifinals')
            ->assertJsonPath('data.matches.6.round_label', 'Finals');

        $this->assertDatabaseCount('bracket_matches', 7);
    }

    public function test_organizer_can_create_a_final_four_bracket_layout(): void
    {
        $pilot = $this->pilot();

        $this->actingAs($pilot['owner'])->postJson($pilot['brackets_url'], [
            'division_id' => $pilot['division']->id,
            'name' => 'Final Four',
            'status' => 'draft',
            'template' => 'single_elimination_4',
        ])->assertCreated()
            ->assertJsonCount(3, 'data.matches')
            ->assertJsonPath('data.matches.0.round_label', 'Semifinals')
            ->assertJsonPath('data.matches.1.match_number', 2)
            ->assertJsonPath('data.matches.2.round_number', 2)
            ->assertJsonPath('data.matches.2.round_label', 'Finals');

        $this->assertDatabaseCount('bracket_matches', 3);
    }

    public function test_initializer_completes_an_existing_bracket_without_overwriting_matchups(): void
    {
        $pilot = $this->pilot();
        $bracket = $this->actingAs($pilot['owner'])->postJson($pilot['brackets_url'], [
            'division_id' => $pilot['division']->id,
            'name' => 'Existing playoffs',
            'status' => 'draft',
        ])->json('data');
        $this->postJson("{$pilot['brackets_url']}/{$bracket['id']}/matches", [
            'round_number' => 1,
            'match_number' => 1,
            'round_label' => 'Quarter Finals',
            'home_team_registration_id' => $pilot['registrations'][0]->id,
            'away_team_registration_id' => $pilot['registrations'][3]->id,
        ])->assertCreated();

        $this->postJson("{$pilot['brackets_url']}/{$bracket['id']}/initialize", [
            'template' => 'single_elimination_8',
        ])->assertOk()
            ->assertJsonCount(7, 'data.matches')
            ->assertJsonPath('data.matches.0.round_label', 'Quarter Finals')
            ->assertJsonPath('data.matches.0.home_team.name', 'Lambakin')
            ->assertJsonPath('data.matches.4.round_label', 'Semifinals')
            ->assertJsonPath('data.matches.6.round_label', 'Finals');
    }

    public function test_bracket_rejects_invalid_winner_duplicate_position_and_cross_tenant_updates(): void
    {
        $pilot = $this->pilot();
        $other = $this->pilot('Other League');
        $bracket = $this->actingAs($pilot['owner'])->postJson($pilot['brackets_url'], [
            'division_id' => $pilot['division']->id,
            'name' => 'Playoffs',
            'status' => 'draft',
        ])->json('data');
        $matchUrl = "{$pilot['brackets_url']}/{$bracket['id']}/matches";
        $payload = [
            'round_number' => 1,
            'match_number' => 1,
            'round_label' => 'Semifinal',
            'home_team_registration_id' => $pilot['registrations'][0]->id,
            'away_team_registration_id' => $pilot['registrations'][1]->id,
        ];

        $this->postJson($matchUrl, [...$payload, 'winner_team_registration_id' => $pilot['registrations'][2]->id])
            ->assertUnprocessable()->assertJsonValidationErrors('winner_team_registration_id');
        $this->postJson($matchUrl, $payload)->assertCreated();
        $this->postJson($matchUrl, $payload)->assertUnprocessable()->assertJsonValidationErrors('match_number');
        $this->actingAs($other['owner'])->patchJson("{$other['brackets_url']}/{$bracket['id']}", ['status' => 'published'])->assertNotFound();
    }

    public function test_scorer_cannot_create_or_publish_brackets(): void
    {
        $pilot = $this->pilot();
        $scorer = $this->member($pilot['organization'], OrganizationRole::Scorer);
        $bracket = $this->actingAs($pilot['owner'])->postJson($pilot['brackets_url'], [
            'division_id' => $pilot['division']->id,
            'name' => 'Protected bracket',
            'status' => 'draft',
        ])->json('data');

        $this->actingAs($scorer)->postJson($pilot['brackets_url'], [
            'division_id' => $pilot['division']->id,
            'name' => 'Unauthorized bracket',
            'status' => 'published',
        ])->assertForbidden();
        $this->postJson("{$pilot['brackets_url']}/{$bracket['id']}/initialize", [
            'template' => 'single_elimination_8',
        ])->assertForbidden();

        $this->assertDatabaseCount('brackets', 1);
        $this->assertDatabaseCount('bracket_matches', 0);
    }

    private function standingRow(SeasonTeamRegistration $registration, string $qualification = 'pending', ?string $notes = null): array
    {
        return [
            'team_registration_id' => $registration->id,
            'qualification_status' => $qualification,
            'notes' => $notes,
        ];
    }

    private function game(array $pilot, int $home, int $away, string $status, ?int $homeScore = null, ?int $awayScore = null): Game
    {
        return Game::create([
            'season_id' => $pilot['season']->id,
            'division_id' => $pilot['division']->id,
            'home_team_registration_id' => $pilot['registrations'][$home]->id,
            'away_team_registration_id' => $pilot['registrations'][$away]->id,
            'scheduled_at' => now()->addDays($home + $away + 1),
            'estimated_duration_minutes' => 90,
            'status' => $status,
            'home_score' => $homeScore ?? 0,
            'away_score' => $awayScore ?? 0,
            'finalized_at' => $status === 'final' ? now() : null,
            'created_by' => $pilot['owner']->id,
            'updated_by' => $pilot['owner']->id,
        ]);
    }

    private function member(Organization $organization, OrganizationRole $role): User
    {
        $user = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function pilot(string $organizationName = 'Jaen Basketball'): array
    {
        $owner = User::factory()->create();
        $slug = str($organizationName)->slug()->toString();
        $organization = Organization::create(['name' => $organizationName, 'slug' => $slug, 'created_by' => $owner->id]);
        OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => OrganizationRole::Owner, 'joined_at' => now()]);
        $competition = Competition::create(['organization_id' => $organization->id, 'name' => 'Pilot League', 'slug' => 'pilot-league', 'type' => 'league', 'status' => 'active']);
        $season = Season::create(['competition_id' => $competition->id, 'name' => '2026', 'slug' => '2026', 'timezone' => 'Asia/Manila', 'format' => 'round_robin', 'status' => 'active', 'max_roster_size' => 20, 'period_count' => 4, 'period_minutes' => 10, 'overtime_minutes' => 5]);
        $division = Division::create(['season_id' => $season->id, 'name' => 'Open Division', 'slug' => 'open-division', 'category' => 'open', 'gender' => 'open', 'is_active' => true]);
        $registrations = collect(['Lambakin', 'San Josef', 'Niyugan', 'Dampulan'])->map(function (string $name) use ($organization, $season, $division): SeasonTeamRegistration {
            $team = Team::create(['organization_id' => $organization->id, 'name' => $name, 'slug' => str($name)->slug(), 'primary_color' => '#f59e0b', 'secondary_color' => '#0f172a', 'status' => 'active']);

            return SeasonTeamRegistration::create(['season_id' => $season->id, 'division_id' => $division->id, 'team_id' => $team->id, 'status' => 'approved']);
        })->all();
        $path = "/api/v1/organizations/{$organization->slug}/competitions/{$competition->slug}/seasons/{$season->slug}";

        return [
            'owner' => $owner,
            'organization' => $organization,
            'competition' => $competition,
            'season' => $season,
            'division' => $division,
            'registrations' => $registrations,
            'standings_url' => $path.'/standings',
            'recalculate_url' => $path.'/standings/recalculate',
            'games_url' => $path.'/games',
            'brackets_url' => $path.'/brackets',
            'public_url' => str_replace('/api/v1/', '/api/v1/public/', $path),
        ];
    }
}
