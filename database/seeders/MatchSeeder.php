<?php

namespace Database\Seeders;

use App\Models\GameMatch;
use App\Models\Round;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Seeder;

/**
 * Official FIFA World Cup 2026 group stage schedule.
 * Source: FIFA official draw (Dec 5, 2025) + confirmed fixture list.
 *
 * Groups A–L (12 groups × 4 teams × 6 matches = 72 group stage matches).
 * All times stored in UTC.
 *
 * UTC conversion reference (summer 2026):
 *   Mexico City CDT  = UTC−5  →  add 5h
 *   US Eastern  EDT  = UTC−4  →  add 4h
 *   US Central  CDT  = UTC−5  →  add 5h
 *   US Pacific  PDT  = UTC−7  →  add 7h
 *   Canada/Toronto EDT = UTC−4
 *   Canada/Vancouver PDT = UTC−7
 */
class MatchSeeder extends Seeder
{
    public function run(): void
    {
        $tournament = Tournament::where('slug', 'world-cup-2026')->firstOrFail();

        $rounds = Round::where('tournament_id', $tournament->id)->get()->keyBy('name');
        $teams  = Team::with('country')->get()->keyBy(fn($t) => $t->country?->iso_code);

        /**
         * Each match:
         *   [round, home, away, utc_datetime, venue]
         *
         * round      = Round name (must match RoundSeeder)
         * home/away  = ISO 3-letter country code
         * utc_datetime = 'YYYY-MM-DD HH:MM:SS' in UTC
         * venue      = Stadium, City
         */
        $matches = [

            // ─────────────────────────────────────────────────────────────
            // GROUP A: Mexico · South Korea · South Africa · Czech Republic
            // ─────────────────────────────────────────────────────────────
            // Matchday 1
            ['Group A', 'MEX', 'RSA', '2026-06-11 18:00:00', 'Estadio Azteca, Mexico City'],
            ['Group A', 'KOR', 'CZE', '2026-06-12 01:00:00', 'Estadio Akron, Guadalajara'],
            // Matchday 2
            ['Group A', 'CZE', 'RSA', '2026-06-18 17:00:00', 'Mercedes-Benz Stadium, Atlanta'],
            ['Group A', 'MEX', 'KOR', '2026-06-19 00:00:00', 'Estadio Akron, Guadalajara'],
            // Matchday 3 (simultaneous)
            ['Group A', 'CZE', 'MEX', '2026-06-24 22:00:00', 'Estadio Azteca, Mexico City'],
            ['Group A', 'RSA', 'KOR', '2026-06-24 22:00:00', 'Estadio BBVA, Monterrey'],

            // ─────────────────────────────────────────────────────────────
            // GROUP B: Canada · Switzerland · Qatar · Bosnia and Herzegovina
            // ─────────────────────────────────────────────────────────────
            // Matchday 1
            ['Group B', 'CAN', 'BIH', '2026-06-12 19:00:00', 'BMO Field, Toronto'],
            ['Group B', 'QAT', 'SUI', '2026-06-13 19:00:00', "Levi's Stadium, Santa Clara"],
            // Matchday 2
            ['Group B', 'SUI', 'BIH', '2026-06-18 19:00:00', 'BC Place, Vancouver'],
            ['Group B', 'CAN', 'QAT', '2026-06-18 19:00:00', 'BMO Field, Toronto'],
            // Matchday 3 (simultaneous)
            ['Group B', 'SUI', 'CAN', '2026-06-24 17:00:00', 'BC Place, Vancouver'],
            ['Group B', 'BIH', 'QAT', '2026-06-24 17:00:00', 'Lumen Field, Seattle'],

            // ─────────────────────────────────────────────────────────────
            // GROUP C: Brazil · Morocco · Scotland · Haiti
            // ─────────────────────────────────────────────────────────────
            // Matchday 1
            ['Group C', 'BRA', 'MAR', '2026-06-13 22:00:00', 'Hard Rock Stadium, Miami'],
            ['Group C', 'HAI', 'SCO', '2026-06-14 01:00:00', 'Gillette Stadium, Boston'],
            // Matchday 2
            ['Group C', 'SCO', 'MAR', '2026-06-19 22:00:00', 'Gillette Stadium, Boston'],
            ['Group C', 'BRA', 'HAI', '2026-06-20 01:00:00', 'Camping World Stadium, Orlando'],
            // Matchday 3 (simultaneous)
            ['Group C', 'SCO', 'BRA', '2026-06-24 22:00:00', 'Gillette Stadium, Boston'],
            ['Group C', 'MAR', 'HAI', '2026-06-24 22:00:00', 'SoFi Stadium, Los Angeles'],

            // ─────────────────────────────────────────────────────────────
            // GROUP D: USA · Paraguay · Australia · Türkiye
            // ─────────────────────────────────────────────────────────────
            // Matchday 1
            ['Group D', 'USA', 'PAR', '2026-06-12 23:00:00', 'NRG Stadium, Houston'],
            ['Group D', 'AUS', 'TUR', '2026-06-14 04:00:00', 'SoFi Stadium, Los Angeles'],
            // Matchday 2
            ['Group D', 'USA', 'AUS', '2026-06-19 17:00:00', 'AT&T Stadium, Dallas'],
            ['Group D', 'TUR', 'PAR', '2026-06-20 04:00:00', 'State Farm Stadium, Glendale'],
            // Matchday 3 (simultaneous)
            ['Group D', 'TUR', 'USA', '2026-06-25 23:00:00', 'Hard Rock Stadium, Miami'],
            ['Group D', 'PAR', 'AUS', '2026-06-26 02:00:00', 'State Farm Stadium, Glendale'],

            // ─────────────────────────────────────────────────────────────
            // GROUP E: Germany · Ecuador · Ivory Coast · Curaçao
            // ─────────────────────────────────────────────────────────────
            // Matchday 1
            ['Group E', 'GER', 'CUW', '2026-06-14 17:00:00', 'AT&T Stadium, Dallas'],
            ['Group E', 'CIV', 'ECU', '2026-06-14 23:00:00', 'Mercedes-Benz Stadium, Atlanta'],
            // Matchday 2
            ['Group E', 'GER', 'CIV', '2026-06-20 21:00:00', 'Arrowhead Stadium, Kansas City'],
            ['Group E', 'ECU', 'CUW', '2026-06-21 02:00:00', 'SoFi Stadium, Los Angeles'],
            // Matchday 3 (simultaneous)
            ['Group E', 'ECU', 'GER', '2026-06-25 21:00:00', 'Arrowhead Stadium, Kansas City'],
            ['Group E', 'CUW', 'CIV', '2026-06-25 21:00:00', 'SoFi Stadium, Los Angeles'],

            // ─────────────────────────────────────────────────────────────
            // GROUP F: Netherlands · Japan · Tunisia · Sweden
            // ─────────────────────────────────────────────────────────────
            // Matchday 1
            ['Group F', 'NED', 'JPN', '2026-06-14 22:00:00', "Levi's Stadium, Santa Clara"],
            ['Group F', 'SWE', 'TUN', '2026-06-15 01:00:00', 'Arrowhead Stadium, Kansas City'],
            // Matchday 2
            ['Group F', 'NED', 'SWE', '2026-06-20 19:00:00', "Levi's Stadium, Santa Clara"],
            ['Group F', 'TUN', 'JPN', '2026-06-21 02:00:00', 'MetLife Stadium, East Rutherford'],
            // Matchday 3 (simultaneous)
            ['Group F', 'JPN', 'SWE', '2026-06-25 17:00:00', 'AT&T Stadium, Dallas'],
            ['Group F', 'TUN', 'NED', '2026-06-25 17:00:00', 'NRG Stadium, Houston'],

            // ─────────────────────────────────────────────────────────────
            // GROUP G: Belgium · Iran · Egypt · New Zealand
            // ─────────────────────────────────────────────────────────────
            // Matchday 1
            ['Group G', 'BEL', 'EGY', '2026-06-15 16:00:00', 'Mercedes-Benz Stadium, Atlanta'],
            ['Group G', 'IRN', 'NZL', '2026-06-15 22:00:00', 'Gillette Stadium, Boston'],
            // Matchday 2
            ['Group G', 'BEL', 'IRN', '2026-06-21 16:00:00', 'Mercedes-Benz Stadium, Atlanta'],
            ['Group G', 'NZL', 'EGY', '2026-06-21 22:00:00', 'Gillette Stadium, Boston'],
            // Matchday 3 (simultaneous)
            ['Group G', 'EGY', 'IRN', '2026-06-26 23:00:00', 'Hard Rock Stadium, Miami'],
            ['Group G', 'NZL', 'BEL', '2026-06-26 23:00:00', 'Gillette Stadium, Boston'],

            // ─────────────────────────────────────────────────────────────
            // GROUP H: Spain · Uruguay · Saudi Arabia · Cape Verde
            // ─────────────────────────────────────────────────────────────
            // Matchday 1
            ['Group H', 'ESP', 'CPV', '2026-06-15 16:00:00', 'Lincoln Financial Field, Philadelphia'],
            ['Group H', 'KSA', 'URU', '2026-06-15 22:00:00', 'MetLife Stadium, East Rutherford'],
            // Matchday 2
            ['Group H', 'ESP', 'KSA', '2026-06-21 16:00:00', 'Lincoln Financial Field, Philadelphia'],
            ['Group H', 'URU', 'CPV', '2026-06-21 22:00:00', 'MetLife Stadium, East Rutherford'],
            // Matchday 3 (simultaneous)
            ['Group H', 'URU', 'ESP', '2026-06-26 22:00:00', 'MetLife Stadium, East Rutherford'],
            ['Group H', 'CPV', 'KSA', '2026-06-26 22:00:00', 'Arrowhead Stadium, Kansas City'],

            // ─────────────────────────────────────────────────────────────
            // GROUP I: France · Senegal · Norway · Iraq
            // ─────────────────────────────────────────────────────────────
            // Matchday 1
            ['Group I', 'FRA', 'SEN', '2026-06-16 22:00:00', 'SoFi Stadium, Los Angeles'],
            ['Group I', 'IRQ', 'NOR', '2026-06-17 02:00:00', 'Hard Rock Stadium, Miami'],
            // Matchday 2
            ['Group I', 'FRA', 'IRQ', '2026-06-22 21:00:00', 'Mercedes-Benz Stadium, Atlanta'],
            ['Group I', 'NOR', 'SEN', '2026-06-23 00:00:00', 'Gillette Stadium, Boston'],
            // Matchday 3 (simultaneous)
            ['Group I', 'NOR', 'FRA', '2026-06-26 19:00:00', 'SoFi Stadium, Los Angeles'],
            ['Group I', 'SEN', 'IRQ', '2026-06-26 19:00:00', 'MetLife Stadium, East Rutherford'],

            // ─────────────────────────────────────────────────────────────
            // GROUP J: Argentina · Austria · Algeria · Jordan
            // ─────────────────────────────────────────────────────────────
            // Matchday 1
            ['Group J', 'ARG', 'ALG', '2026-06-17 00:00:00', 'Gillette Stadium, Boston'],
            ['Group J', 'AUT', 'JOR', '2026-06-17 01:00:00', 'MetLife Stadium, East Rutherford'],
            // Matchday 2
            ['Group J', 'ARG', 'AUT', '2026-06-22 16:00:00', 'MetLife Stadium, East Rutherford'],
            ['Group J', 'JOR', 'ALG', '2026-06-23 01:00:00', 'Arrowhead Stadium, Kansas City'],
            // Matchday 3 (simultaneous)
            ['Group J', 'JOR', 'ARG', '2026-06-27 01:00:00', 'Gillette Stadium, Boston'],
            ['Group J', 'ALG', 'AUT', '2026-06-27 01:00:00', 'Arrowhead Stadium, Kansas City'],

            // ─────────────────────────────────────────────────────────────
            // GROUP K: Portugal · Colombia · Uzbekistan · DR Congo
            // ─────────────────────────────────────────────────────────────
            // Matchday 1
            ['Group K', 'POR', 'COD', '2026-06-17 17:00:00', 'AT&T Stadium, Dallas'],
            ['Group K', 'UZB', 'COL', '2026-06-18 03:00:00', 'SoFi Stadium, Los Angeles'],
            // Matchday 2
            ['Group K', 'POR', 'UZB', '2026-06-23 16:00:00', "Levi's Stadium, Santa Clara"],
            ['Group K', 'COL', 'COD', '2026-06-24 00:00:00', 'Gillette Stadium, Boston'],
            // Matchday 3 (simultaneous)
            ['Group K', 'COL', 'POR', '2026-06-28 00:30:00', 'SoFi Stadium, Los Angeles'],
            ['Group K', 'COD', 'UZB', '2026-06-28 00:30:00', 'MetLife Stadium, East Rutherford'],

            // ─────────────────────────────────────────────────────────────
            // GROUP L: England · Croatia · Panama · Ghana
            // ─────────────────────────────────────────────────────────────
            // Matchday 1
            ['Group L', 'ENG', 'CRO', '2026-06-17 22:00:00', "Levi's Stadium, Santa Clara"],
            ['Group L', 'GHA', 'PAN', '2026-06-17 23:00:00', 'Mercedes-Benz Stadium, Atlanta'],
            // Matchday 2
            ['Group L', 'ENG', 'GHA', '2026-06-23 22:00:00', 'SoFi Stadium, Los Angeles'],
            ['Group L', 'PAN', 'CRO', '2026-06-23 23:00:00', 'Hard Rock Stadium, Miami'],
            // Matchday 3 (simultaneous)
            ['Group L', 'PAN', 'ENG', '2026-06-27 21:00:00', 'SoFi Stadium, Los Angeles'],
            ['Group L', 'CRO', 'GHA', '2026-06-27 21:00:00', "Levi's Stadium, Santa Clara"],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($matches as [$roundName, $homeCode, $awayCode, $datetime, $venue]) {
            $round    = $rounds[$roundName] ?? null;
            $homeTeam = $teams[$homeCode] ?? null;
            $awayTeam = $teams[$awayCode] ?? null;

            if (!$round) {
                $this->command->warn("Round not found: {$roundName}");
                $skipped++;
                continue;
            }
            if (!$homeTeam) {
                $this->command->warn("Team not found: {$homeCode}");
                $skipped++;
                continue;
            }
            if (!$awayTeam) {
                $this->command->warn("Team not found: {$awayCode}");
                $skipped++;
                continue;
            }

            $match = GameMatch::firstOrCreate(
                [
                    'round_id'     => $round->id,
                    'home_team_id' => $homeTeam->id,
                    'away_team_id' => $awayTeam->id,
                ],
                [
                    'scheduled_at'         => $datetime,
                    'prediction_closes_at' => $datetime,
                    'venue'                => $venue,
                    'status'               => 'scheduled',
                ]
            );

            $match->wasRecentlyCreated ? $created++ : $skipped++;
        }

        $this->command->info("MatchSeeder done — {$created} created, {$skipped} already existed.");
        $this->command->info('Total group stage matches in DB: ' . GameMatch::whereHas('round', fn($q) => $q->where('tournament_id', $tournament->id))->count());
    }
}
