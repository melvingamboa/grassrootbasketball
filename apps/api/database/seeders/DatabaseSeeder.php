<?php

namespace Database\Seeders;

use App\Enums\OrganizationRole;
use App\Models\AdministrativeArea;
use App\Models\Announcement;
use App\Models\Bracket;
use App\Models\Competition;
use App\Models\Division;
use App\Models\Game;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Season;
use App\Models\SeasonTeamRegistration;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Services\StandingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(StandingService $standings): void
    {
        if (! app()->environment('local')) {
            return;
        }

        User::updateOrCreate(
            ['email' => 'admin@grassroots.test'],
            [
                'name' => 'Platform Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_platform_admin' => true,
            ],
        );

        $organizer = User::updateOrCreate(
            ['email' => 'organizer@grassroots.test'],
            [
                'name' => 'Demo Organizer',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_platform_admin' => false,
            ],
        );

        $organization = Organization::updateOrCreate(
            ['slug' => 'jaen-community-basketball'],
            [
                'name' => 'Jaen Community Basketball',
                'description' => 'Local P2 demonstration workspace for organizer access.',
                'created_by' => $organizer->id,
            ],
        );

        OrganizationMembership::updateOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $organizer->id],
            ['role' => OrganizationRole::Owner, 'joined_at' => now()],
        );

        $province = AdministrativeArea::firstOrCreate(
            ['parent_id' => null, 'type' => 'province', 'name' => 'Nueva Ecija'],
            ['code' => 'PH-NUE'],
        );
        $municipality = AdministrativeArea::firstOrCreate(
            ['parent_id' => $province->id, 'type' => 'municipality', 'name' => 'Jaen'],
        );
        $barangay = AdministrativeArea::firstOrCreate(
            ['parent_id' => $municipality->id, 'type' => 'barangay', 'name' => 'Lambakin'],
        );
        AdministrativeArea::firstOrCreate(
            ['parent_id' => $barangay->id, 'type' => 'purok', 'name' => 'Purok 1'],
        );

        $venue = Venue::updateOrCreate(
            ['organization_id' => $organization->id, 'slug' => 'jaen-municipal-gym'],
            [
                'administrative_area_id' => $municipality->id,
                'name' => 'Jaen Municipal Gym',
                'address' => 'Jaen, Nueva Ecija',
                'status' => 'active',
            ],
        );
        $competition = Competition::updateOrCreate(
            ['organization_id' => $organization->id, 'slug' => 'jaen-inter-purok-basketball'],
            [
                'administrative_area_id' => $municipality->id,
                'name' => 'Jaen Inter-Purok Basketball',
                'type' => 'league',
                'status' => 'active',
                'description' => 'Official schedules, announcements, rosters, and results for the pilot competition.',
            ],
        );
        $season = Season::updateOrCreate(
            ['competition_id' => $competition->id, 'slug' => '2026-pilot-season'],
            [
                'primary_venue_id' => $venue->id,
                'name' => '2026 Pilot Season',
                'timezone' => 'Asia/Manila',
                'format' => 'round_robin',
                'status' => 'active',
                'max_roster_size' => 20,
                'period_count' => 4,
                'period_minutes' => 10,
                'overtime_minutes' => 5,
            ],
        );
        Division::updateOrCreate(
            ['season_id' => $season->id, 'slug' => 'open-division'],
            ['name' => 'Open Division', 'category' => 'open', 'gender' => 'open', 'is_active' => true],
        );

        $division = Division::where('season_id', $season->id)->where('slug', 'open-division')->firstOrFail();
        $team = Team::updateOrCreate(
            ['organization_id' => $organization->id, 'slug' => 'lambakin-ballers'],
            [
                'administrative_area_id' => $barangay->id,
                'name' => 'Lambakin Ballers',
                'short_name' => 'LMB',
                'primary_color' => '#f59e0b',
                'secondary_color' => '#0f172a',
                'status' => 'active',
            ],
        );
        $teamRegistration = SeasonTeamRegistration::updateOrCreate(
            ['season_id' => $season->id, 'team_id' => $team->id],
            ['division_id' => $division->id, 'status' => 'approved'],
        );
        $players = collect([
            ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'jersey_number' => 23, 'position' => 'guard'],
            ['first_name' => 'Mark', 'last_name' => 'Santos', 'jersey_number' => 7, 'position' => 'forward'],
            ['first_name' => 'Carlo', 'last_name' => 'Reyes', 'jersey_number' => 11, 'position' => 'center'],
        ]);
        $players->each(function (array $data) use ($organization, $teamRegistration): void {
            $player = Player::firstOrCreate(
                ['organization_id' => $organization->id, 'first_name' => $data['first_name'], 'last_name' => $data['last_name']],
                ['is_active' => true],
            );
            PlayerRegistration::updateOrCreate(
                ['season_team_registration_id' => $teamRegistration->id, 'player_id' => $player->id],
                ['jersey_number' => $data['jersey_number'], 'position' => $data['position'], 'status' => 'approved'],
            );
        });

        $opponents = collect([
            ['name' => 'San Josef Flyers', 'slug' => 'san-josef-flyers', 'short_name' => 'SJF', 'primary_color' => '#2563eb'],
            ['name' => 'Niyugan Warriors', 'slug' => 'niyugan-warriors', 'short_name' => 'NYG', 'primary_color' => '#e11d48'],
            ['name' => 'Purok DOS', 'slug' => 'purok-dos', 'short_name' => 'DOS', 'primary_color' => '#f59e0b'],
            ['name' => 'Purok 4', 'slug' => 'purok-4', 'short_name' => 'QTRO', 'primary_color' => '#ad2e6c'],
            ['name' => 'Hustler', 'slug' => 'hustler', 'short_name' => 'HUST', 'primary_color' => '#f59e0b'],
            ['name' => 'Tres Boleros', 'slug' => 'tres-boleros', 'short_name' => 'TRES', 'primary_color' => '#f59e0b'],
            ['name' => 'VETERANS', 'slug' => 'veterans', 'short_name' => 'VET', 'primary_color' => '#f59e0b'],
        ])->map(function (array $data) use ($organization, $season, $division): SeasonTeamRegistration {
            $team = Team::updateOrCreate(
                ['organization_id' => $organization->id, 'slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'short_name' => $data['short_name'],
                    'primary_color' => $data['primary_color'],
                    'secondary_color' => '#0f172a',
                    'status' => 'active',
                ],
            );

            return SeasonTeamRegistration::updateOrCreate(
                ['season_id' => $season->id, 'team_id' => $team->id],
                ['division_id' => $division->id, 'status' => 'approved'],
            );
        });

        $tomorrowAtSix = now($season->timezone)
            ->addDay()
            ->startOfDay()
            ->setTime(18, 0)
            ->setTimezone(config('app.timezone'));
        $registrations = collect([$teamRegistration, ...$opponents]);
        $season->games()->delete();
        $gameNumber = 0;
        for ($homeIndex = 0; $homeIndex < $registrations->count() - 1; $homeIndex++) {
            for ($awayIndex = $homeIndex + 1; $awayIndex < $registrations->count(); $awayIndex++) {
                Game::create([
                    'season_id' => $season->id,
                    'division_id' => $division->id,
                    'home_team_registration_id' => $registrations[$homeIndex]->id,
                    'away_team_registration_id' => $registrations[$awayIndex]->id,
                    'venue_id' => $venue->id,
                    'scheduled_at' => $tomorrowAtSix->subDays(35 - $gameNumber),
                    'estimated_duration_minutes' => 90,
                    'round' => 'Elimination game '.($gameNumber + 1),
                    'status' => 'final',
                    'home_score' => 92 - $homeIndex + (($awayIndex * 3) % 7),
                    'away_score' => 70 + (($homeIndex + $awayIndex) % 9),
                    'status_reason' => null,
                    'finalized_at' => now(),
                    'created_by' => $organizer->id,
                    'updated_by' => $organizer->id,
                ]);
                $gameNumber++;
            }
        }
        $computedStandings = $standings->recalculate($season, $division->id, $organizer);
        $computedStandings->each(function ($standing): void {
            $standing->update([
                'qualification_status' => 'qualified',
                'notes' => 'Advanced to the quarterfinals',
            ]);
        });
        $playoffTeams = $computedStandings->take(8)->values();
        $bracket = Bracket::updateOrCreate(
            ['season_id' => $season->id, 'division_id' => $division->id, 'name' => 'Pilot playoffs'],
            ['status' => 'published', 'created_by' => $organizer->id, 'updated_by' => $organizer->id],
        );
        $bracket->matches()->delete();
        $bracket->matches()->createMany([
            [
                'round_number' => 1,
                'match_number' => 1,
                'round_label' => 'Quarterfinals',
                'home_team_registration_id' => $playoffTeams[0]->team_registration_id,
                'away_team_registration_id' => $playoffTeams[7]->team_registration_id,
            ],
            [
                'round_number' => 1,
                'match_number' => 2,
                'round_label' => 'Quarterfinals',
                'home_team_registration_id' => $playoffTeams[3]->team_registration_id,
                'away_team_registration_id' => $playoffTeams[4]->team_registration_id,
            ],
            [
                'round_number' => 1,
                'match_number' => 3,
                'round_label' => 'Quarterfinals',
                'home_team_registration_id' => $playoffTeams[1]->team_registration_id,
                'away_team_registration_id' => $playoffTeams[6]->team_registration_id,
            ],
            [
                'round_number' => 1,
                'match_number' => 4,
                'round_label' => 'Quarterfinals',
                'home_team_registration_id' => $playoffTeams[2]->team_registration_id,
                'away_team_registration_id' => $playoffTeams[5]->team_registration_id,
            ],
            ['round_number' => 2, 'match_number' => 1, 'round_label' => 'Semifinals'],
            ['round_number' => 2, 'match_number' => 2, 'round_label' => 'Semifinals'],
            ['round_number' => 3, 'match_number' => 1, 'round_label' => 'Finals'],
        ]);
        Announcement::updateOrCreate(
            ['season_id' => $season->id, 'title' => 'Pilot schedule is now available'],
            [
                'body' => 'Check the game schedule before travelling to the venue. Any official change will appear here.',
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $organizer->id,
                'updated_by' => $organizer->id,
            ],
        );
    }
}
