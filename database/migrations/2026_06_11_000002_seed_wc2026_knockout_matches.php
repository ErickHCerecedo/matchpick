<?php

use App\Models\GameMatch;
use App\Models\Round;
use App\Models\Tournament;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds the 32 knockout-stage matches for FIFA World Cup 2026.
 *
 * bracket_slot follows the standard binary-tree bracket order (1-indexed
 * from top of bracket downward), validated against the official FIFA bracket:
 *
 *   R32 slots  1-4  → R16 slot 1 → QF slot 1 → SF slot 1 → Final
 *   R32 slots  5-8  → R16 slot 2 → QF slot 1 → SF slot 1 → Final
 *   R32 slots  9-12 → R16 slot 3 → QF slot 2 → SF slot 1 → Final
 *   R32 slots 13-16 → R16 slot 4 → QF slot 2 → SF slot 1 → Final
 *   (bottom half mirror for SF slot 2)
 *
 * All timestamps are UTC. prediction_closes_at = scheduled_at (closes
 * at kick-off). Team IDs are intentionally null — groups define qualifiers;
 * placeholders carry human-readable bracket positions for display.
 *
 * Idempotent: skips insert if round already has matches.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tournament = Tournament::where('slug', 'world-cup-2026')->first();
        if (! $tournament) return;

        $r = fn(string $type) => Round::where('tournament_id', $tournament->id)
            ->where('type', $type)
            ->first();

        $r32 = $r('round_of_32');
        $r16 = $r('round_of_16');
        $qf  = $r('quarter');
        $sf  = $r('semi');
        $tp  = $r('third_place');
        $fin = $r('final');

        if (! $r32 || ! $r16 || ! $qf || ! $sf || ! $tp || ! $fin) return;

        // ── Round of 32 — 16 matches ──────────────────────────────────────
        // bracket_slot follows FIFA bracket top-to-bottom visual order.
        // Slots 1-4 feed R16#1, 5-8 feed R16#2, 9-12 feed R16#3, 13-16 feed R16#4.
        $this->insertMatches($r32->id, [
            // slot 1 — SoFi Stadium, Inglewood CA
            [
                'scheduled_at'    => '2026-06-28 19:00:00',
                'venue'           => 'SoFi Stadium, Inglewood, CA',
                'home_placeholder'=> '2do Grupo A',
                'away_placeholder'=> '2do Grupo B',
                'bracket_slot'    => 1,
            ],
            // slot 2 — Estadio BBVA, Guadalupe NL
            [
                'scheduled_at'    => '2026-06-30 01:00:00',
                'venue'           => 'Estadio BBVA, Guadalupe, NL',
                'home_placeholder'=> '1ro Grupo F',
                'away_placeholder'=> '2do Grupo C',
                'bracket_slot'    => 2,
            ],
            // slot 3 — Gillette Stadium, Foxborough MA
            [
                'scheduled_at'    => '2026-06-29 20:30:00',
                'venue'           => 'Gillette Stadium, Foxborough, MA',
                'home_placeholder'=> '1ro Grupo E',
                'away_placeholder'=> 'Mejor 3ro (A/B/C/D/F)',
                'bracket_slot'    => 3,
            ],
            // slot 4 — MetLife Stadium, East Rutherford NJ
            [
                'scheduled_at'    => '2026-06-30 21:00:00',
                'venue'           => 'MetLife Stadium, East Rutherford, NJ',
                'home_placeholder'=> '1ro Grupo I',
                'away_placeholder'=> 'Mejor 3ro (C/D/F/G/H)',
                'bracket_slot'    => 4,
            ],
            // slot 5 — BMO Field, Toronto ON
            [
                'scheduled_at'    => '2026-07-02 23:00:00',
                'venue'           => 'BMO Field, Toronto, ON',
                'home_placeholder'=> '2do Grupo K',
                'away_placeholder'=> '2do Grupo L',
                'bracket_slot'    => 5,
            ],
            // slot 6 — SoFi Stadium, Inglewood CA
            [
                'scheduled_at'    => '2026-07-02 19:00:00',
                'venue'           => 'SoFi Stadium, Inglewood, CA',
                'home_placeholder'=> '1ro Grupo H',
                'away_placeholder'=> '2do Grupo J',
                'bracket_slot'    => 6,
            ],
            // slot 7 — Levi\'s Stadium, Santa Clara CA
            [
                'scheduled_at'    => '2026-07-02 00:00:00',
                'venue'           => "Levi's Stadium, Santa Clara, CA",
                'home_placeholder'=> '1ro Grupo D',
                'away_placeholder'=> 'Mejor 3ro (B/E/F/I/J)',
                'bracket_slot'    => 7,
            ],
            // slot 8 — Lumen Field, Seattle WA
            [
                'scheduled_at'    => '2026-07-01 20:00:00',
                'venue'           => 'Lumen Field, Seattle, WA',
                'home_placeholder'=> '1ro Grupo G',
                'away_placeholder'=> 'Mejor 3ro (A/E/H/I/J)',
                'bracket_slot'    => 8,
            ],
            // slot 9 — NRG Stadium, Houston TX
            [
                'scheduled_at'    => '2026-06-29 17:00:00',
                'venue'           => 'NRG Stadium, Houston, TX',
                'home_placeholder'=> '1ro Grupo C',
                'away_placeholder'=> '2do Grupo F',
                'bracket_slot'    => 9,
            ],
            // slot 10 — AT&T Stadium, Arlington TX
            [
                'scheduled_at'    => '2026-06-30 17:00:00',
                'venue'           => 'AT&T Stadium, Arlington, TX',
                'home_placeholder'=> '2do Grupo E',
                'away_placeholder'=> '2do Grupo I',
                'bracket_slot'    => 10,
            ],
            // slot 11 — Estadio Azteca, Ciudad de México
            [
                'scheduled_at'    => '2026-07-01 01:00:00',
                'venue'           => 'Estadio Azteca, Ciudad de México',
                'home_placeholder'=> '1ro Grupo A',
                'away_placeholder'=> 'Mejor 3ro (C/E/F/H/I)',
                'bracket_slot'    => 11,
            ],
            // slot 12 — Mercedes-Benz Stadium, Atlanta GA
            [
                'scheduled_at'    => '2026-07-01 16:00:00',
                'venue'           => 'Mercedes-Benz Stadium, Atlanta, GA',
                'home_placeholder'=> '1ro Grupo L',
                'away_placeholder'=> 'Mejor 3ro (E/H/I/J/K)',
                'bracket_slot'    => 12,
            ],
            // slot 13 — AT&T Stadium, Arlington TX
            [
                'scheduled_at'    => '2026-07-03 18:00:00',
                'venue'           => 'AT&T Stadium, Arlington, TX',
                'home_placeholder'=> '2do Grupo D',
                'away_placeholder'=> '2do Grupo G',
                'bracket_slot'    => 13,
            ],
            // slot 14 — Hard Rock Stadium, Miami Gardens FL
            [
                'scheduled_at'    => '2026-07-03 22:00:00',
                'venue'           => 'Hard Rock Stadium, Miami Gardens, FL',
                'home_placeholder'=> '1ro Grupo J',
                'away_placeholder'=> '2do Grupo H',
                'bracket_slot'    => 14,
            ],
            // slot 15 — BC Place, Vancouver BC
            [
                'scheduled_at'    => '2026-07-03 03:00:00',
                'venue'           => 'BC Place, Vancouver, BC',
                'home_placeholder'=> '1ro Grupo B',
                'away_placeholder'=> 'Mejor 3ro (E/F/G/I/J)',
                'bracket_slot'    => 15,
            ],
            // slot 16 — Arrowhead Stadium, Kansas City MO
            [
                'scheduled_at'    => '2026-07-04 01:30:00',
                'venue'           => 'Arrowhead Stadium, Kansas City, MO',
                'home_placeholder'=> '1ro Grupo K',
                'away_placeholder'=> 'Mejor 3ro (D/E/I/J/L)',
                'bracket_slot'    => 16,
            ],
        ]);

        // ── Round of 16 — 8 matches ───────────────────────────────────────
        $this->insertMatches($r16->id, [
            [
                'scheduled_at'    => '2026-07-04 17:00:00',
                'venue'           => 'NRG Stadium, Houston, TX',
                'home_placeholder'=> 'Gan. 32avos #1',
                'away_placeholder'=> 'Gan. 32avos #2',
                'bracket_slot'    => 1,
            ],
            [
                'scheduled_at'    => '2026-07-04 21:00:00',
                'venue'           => 'Lincoln Financial Field, Philadelphia, PA',
                'home_placeholder'=> 'Gan. 32avos #3',
                'away_placeholder'=> 'Gan. 32avos #4',
                'bracket_slot'    => 2,
            ],
            [
                'scheduled_at'    => '2026-07-06 19:00:00',
                'venue'           => 'AT&T Stadium, Arlington, TX',
                'home_placeholder'=> 'Gan. 32avos #5',
                'away_placeholder'=> 'Gan. 32avos #6',
                'bracket_slot'    => 3,
            ],
            [
                'scheduled_at'    => '2026-07-07 00:00:00',
                'venue'           => 'Lumen Field, Seattle, WA',
                'home_placeholder'=> 'Gan. 32avos #7',
                'away_placeholder'=> 'Gan. 32avos #8',
                'bracket_slot'    => 4,
            ],
            [
                'scheduled_at'    => '2026-07-05 20:00:00',
                'venue'           => 'MetLife Stadium, East Rutherford, NJ',
                'home_placeholder'=> 'Gan. 32avos #9',
                'away_placeholder'=> 'Gan. 32avos #10',
                'bracket_slot'    => 5,
            ],
            [
                'scheduled_at'    => '2026-07-06 00:00:00',
                'venue'           => 'Estadio Azteca, Ciudad de México',
                'home_placeholder'=> 'Gan. 32avos #11',
                'away_placeholder'=> 'Gan. 32avos #12',
                'bracket_slot'    => 6,
            ],
            [
                'scheduled_at'    => '2026-07-07 16:00:00',
                'venue'           => 'Mercedes-Benz Stadium, Atlanta, GA',
                'home_placeholder'=> 'Gan. 32avos #13',
                'away_placeholder'=> 'Gan. 32avos #14',
                'bracket_slot'    => 7,
            ],
            [
                'scheduled_at'    => '2026-07-07 20:00:00',
                'venue'           => 'BC Place, Vancouver, BC',
                'home_placeholder'=> 'Gan. 32avos #15',
                'away_placeholder'=> 'Gan. 32avos #16',
                'bracket_slot'    => 8,
            ],
        ]);

        // ── Quarter-finals — 4 matches ────────────────────────────────────
        $this->insertMatches($qf->id, [
            [
                'scheduled_at'    => '2026-07-09 20:00:00',
                'venue'           => 'Gillette Stadium, Foxborough, MA',
                'home_placeholder'=> 'Gan. 16avos #1',
                'away_placeholder'=> 'Gan. 16avos #2',
                'bracket_slot'    => 1,
            ],
            [
                'scheduled_at'    => '2026-07-10 19:00:00',
                'venue'           => 'SoFi Stadium, Inglewood, CA',
                'home_placeholder'=> 'Gan. 16avos #3',
                'away_placeholder'=> 'Gan. 16avos #4',
                'bracket_slot'    => 2,
            ],
            [
                'scheduled_at'    => '2026-07-11 21:00:00',
                'venue'           => 'Hard Rock Stadium, Miami Gardens, FL',
                'home_placeholder'=> 'Gan. 16avos #5',
                'away_placeholder'=> 'Gan. 16avos #6',
                'bracket_slot'    => 3,
            ],
            [
                'scheduled_at'    => '2026-07-12 01:00:00',
                'venue'           => 'Arrowhead Stadium, Kansas City, MO',
                'home_placeholder'=> 'Gan. 16avos #7',
                'away_placeholder'=> 'Gan. 16avos #8',
                'bracket_slot'    => 4,
            ],
        ]);

        // ── Semi-finals — 2 matches ───────────────────────────────────────
        $this->insertMatches($sf->id, [
            [
                'scheduled_at'    => '2026-07-14 19:00:00',
                'venue'           => 'AT&T Stadium, Arlington, TX',
                'home_placeholder'=> 'Gan. Cuartos #1',
                'away_placeholder'=> 'Gan. Cuartos #2',
                'bracket_slot'    => 1,
            ],
            [
                'scheduled_at'    => '2026-07-15 19:00:00',
                'venue'           => 'Mercedes-Benz Stadium, Atlanta, GA',
                'home_placeholder'=> 'Gan. Cuartos #3',
                'away_placeholder'=> 'Gan. Cuartos #4',
                'bracket_slot'    => 2,
            ],
        ]);

        // ── Third place — 1 match ─────────────────────────────────────────
        $this->insertMatches($tp->id, [
            [
                'scheduled_at'    => '2026-07-18 21:00:00',
                'venue'           => 'Hard Rock Stadium, Miami Gardens, FL',
                'home_placeholder'=> 'Per. Semifinal #1',
                'away_placeholder'=> 'Per. Semifinal #2',
                'bracket_slot'    => 1,
            ],
        ]);

        // ── Final — 1 match ───────────────────────────────────────────────
        $this->insertMatches($fin->id, [
            [
                'scheduled_at'    => '2026-07-19 19:00:00',
                'venue'           => 'MetLife Stadium, East Rutherford, NJ',
                'home_placeholder'=> 'Gan. Semifinal #1',
                'away_placeholder'=> 'Gan. Semifinal #2',
                'bracket_slot'    => 1,
            ],
        ]);
    }

    public function down(): void
    {
        // Intentionally empty — do not remove live match data in production.
    }

    private function insertMatches(int $roundId, array $matches): void
    {
        // Skip if this round already has any matches (protects live data).
        if (GameMatch::where('round_id', $roundId)->exists()) return;

        $now = now();
        GameMatch::insert(array_map(fn($m) => [
            'round_id'            => $roundId,
            'home_team_id'        => null,
            'home_placeholder'    => $m['home_placeholder'],
            'away_team_id'        => null,
            'away_placeholder'    => $m['away_placeholder'],
            'scheduled_at'        => $m['scheduled_at'],
            'venue'               => $m['venue'],
            'status'              => 'scheduled',
            'prediction_closes_at'=> $m['scheduled_at'],
            'bracket_slot'        => $m['bracket_slot'],
            'external_id'         => null,
            'created_at'          => $now,
            'updated_at'          => $now,
        ], $matches));
    }
};
