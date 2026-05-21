<?php

namespace Database\Seeders;

use App\Models\Match;
use App\Models\Round;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Seeder;

class MatchSeeder extends Seeder
{
    /**
     * Group assignments for FIFA World Cup 2026.
     * Each group has 4 teams; 6 matches per group (round-robin).
     *
     * Groups A-L with 4 teams each = 48 teams total
     * Match pairs: A vs B, A vs C, A vs D, B vs C, B vs D, C vs D
     */
    public function run(): void
    {
        $tournament = Tournament::where('slug', 'world-cup-2026')->firstOrFail();

        // Load rounds keyed by name
        $rounds = Round::where('tournament_id', $tournament->id)
            ->get()
            ->keyBy('name');

        // Load teams keyed by country ISO code
        $teams = Team::with('country')->get()->keyBy(fn($t) => $t->country?->iso_code);

        // Group definitions: Group Name => [iso_code A, iso_code B, iso_code C, iso_code D]
        $groups = [
            'Group A' => ['MEX', 'ECU', 'CIV', 'HUN'],
            'Group B' => ['USA', 'GER', 'MAR', 'JPN'],
            'Group C' => ['CAN', 'POR', 'ESP', 'NZL'],
            'Group D' => ['BRA', 'FRA', 'ENG', 'SEN'],
            'Group E' => ['ARG', 'NED', 'ITA', 'CRO'],
            'Group F' => ['COL', 'URU', 'BEL', 'SUI'],
            'Group G' => ['IRN', 'KSA', 'QAT', 'AUS'],
            'Group H' => ['KOR', 'NGA', 'CMR', 'GHA'],
            'Group I' => ['TUR', 'DEN', 'SRB', 'TUN'],
            'Group J' => ['ALG', 'EGY', 'CRC', 'HON'],
            'Group K' => ['PAN', 'PAR', 'JOR', 'UZB'],
            'Group L' => ['SCO', 'AUT', 'UKR', 'VEN'],
        ];

        // Venue assignments per group (host cities spread across USA, Canada, Mexico)
        $venues = [
            'Group A' => ['Estadio Azteca, Mexico City', 'Estadio BBVA, Monterrey', 'Estadio Akron, Guadalajara'],
            'Group B' => ['MetLife Stadium, New York/New Jersey', 'AT&T Stadium, Dallas', 'SoFi Stadium, Los Angeles'],
            'Group C' => ['BC Place, Vancouver', 'BMO Field, Toronto', 'Lumen Field, Seattle'],
            'Group D' => ['Hard Rock Stadium, Miami', 'Gillette Stadium, Boston', 'Lincoln Financial Field, Philadelphia'],
            'Group E' => ['Rose Bowl, Los Angeles', 'Allegiant Stadium, Las Vegas', 'State Farm Stadium, Phoenix'],
            'Group F' => ['AT&T Stadium, Dallas', 'NRG Stadium, Houston', 'Arrowhead Stadium, Kansas City'],
            'Group G' => ['SoFi Stadium, Los Angeles', 'Levi\'s Stadium, San Francisco', 'Dignity Health Sports Park, Los Angeles'],
            'Group H' => ['MetLife Stadium, New York/New Jersey', 'Bank of America Stadium, Charlotte', 'Camping World Stadium, Orlando'],
            'Group I' => ['Lincoln Financial Field, Philadelphia', 'Empower Field, Denver', 'Q2 Stadium, Austin'],
            'Group J' => ['Estadio Azteca, Mexico City', 'NRG Stadium, Houston', 'AT&T Stadium, Dallas'],
            'Group K' => ['BMO Field, Toronto', 'Stade de Saputo, Montreal', 'Commonwealth Stadium, Edmonton'],
            'Group L' => ['BC Place, Vancouver', 'Lumen Field, Seattle', 'Rose Bowl, Los Angeles'],
        ];

        // Base dates for group stages: June 11 - July 2, 2026
        // Each group gets dates spread across the group stage window
        $groupDates = [
            'Group A' => ['2026-06-11', '2026-06-15', '2026-06-19', '2026-06-14', '2026-06-18', '2026-06-22'],
            'Group B' => ['2026-06-11', '2026-06-15', '2026-06-19', '2026-06-14', '2026-06-18', '2026-06-22'],
            'Group C' => ['2026-06-12', '2026-06-16', '2026-06-20', '2026-06-15', '2026-06-19', '2026-06-23'],
            'Group D' => ['2026-06-12', '2026-06-16', '2026-06-20', '2026-06-15', '2026-06-19', '2026-06-23'],
            'Group E' => ['2026-06-13', '2026-06-17', '2026-06-21', '2026-06-16', '2026-06-20', '2026-06-24'],
            'Group F' => ['2026-06-13', '2026-06-17', '2026-06-21', '2026-06-16', '2026-06-20', '2026-06-24'],
            'Group G' => ['2026-06-14', '2026-06-18', '2026-06-22', '2026-06-17', '2026-06-21', '2026-06-25'],
            'Group H' => ['2026-06-14', '2026-06-18', '2026-06-22', '2026-06-17', '2026-06-21', '2026-06-25'],
            'Group I' => ['2026-06-15', '2026-06-19', '2026-06-23', '2026-06-18', '2026-06-22', '2026-06-26'],
            'Group J' => ['2026-06-15', '2026-06-19', '2026-06-23', '2026-06-18', '2026-06-22', '2026-06-26'],
            'Group K' => ['2026-06-16', '2026-06-20', '2026-06-24', '2026-06-19', '2026-06-23', '2026-06-27'],
            'Group L' => ['2026-06-16', '2026-06-20', '2026-06-24', '2026-06-19', '2026-06-23', '2026-06-27'],
        ];

        $kickoffTimes = ['12:00:00', '15:00:00', '18:00:00', '21:00:00'];

        foreach ($groups as $groupName => $teamCodes) {
            $round = $rounds[$groupName] ?? null;
            if (!$round) {
                $this->command->warn("Round not found: {$groupName}");
                continue;
            }

            $groupTeams = [];
            foreach ($teamCodes as $code) {
                $team = $teams[$code] ?? null;
                if (!$team) {
                    $this->command->warn("Team not found for ISO code: {$code}");
                    continue;
                }
                $groupTeams[] = $team;
            }

            if (count($groupTeams) < 4) {
                $this->command->warn("Not enough teams for group: {$groupName}");
                continue;
            }

            // Generate all 6 matchups (round-robin pairs)
            // A vs B, A vs C, A vs D, B vs C, B vs D, C vs D
            $matchups = [
                [0, 1], // A vs B
                [2, 3], // C vs D
                [0, 2], // A vs C
                [1, 3], // B vs D
                [0, 3], // A vs D
                [1, 2], // B vs C
            ];

            $groupVenues = $venues[$groupName];
            $dates = $groupDates[$groupName];

            foreach ($matchups as $matchIndex => $pair) {
                $homeTeam = $groupTeams[$pair[0]];
                $awayTeam = $groupTeams[$pair[1]];

                $venue = $groupVenues[$matchIndex % count($groupVenues)];
                $date = $dates[$matchIndex];
                $time = $kickoffTimes[$matchIndex % count($kickoffTimes)];
                $scheduledAt = $date . ' ' . $time;

                Match::firstOrCreate(
                    [
                        'round_id' => $round->id,
                        'home_team_id' => $homeTeam->id,
                        'away_team_id' => $awayTeam->id,
                    ],
                    [
                        'scheduled_at' => $scheduledAt,
                        'prediction_closes_at' => $scheduledAt,
                        'venue' => $venue,
                        'status' => 'scheduled',
                    ]
                );
            }
        }

        $this->command->info('Match seeder completed. ' . Match::count() . ' matches created.');
    }
}
