<?php

namespace App\Http\Controllers;

use App\Http\Resources\TournamentResource;
use App\Models\GameMatch;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentController extends Controller
{
    public function index(): JsonResponse
    {
        $tournaments = Tournament::where('is_active', true)->get();
        return response()->json(['data' => TournamentResource::collection($tournaments)]);
    }

    public function show(string $slug): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->with('rounds')->firstOrFail();
        return response()->json(['data' => new TournamentResource($tournament)]);
    }

    public function matches(Request $request, string $slug): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();

        $query = $tournament->rounds()->with([
            'matches.homeTeam.country',
            'matches.awayTeam.country',
            'matches.result',
        ]);

        if ($request->has('round_type')) {
            $query->where('type', $request->round_type);
        }

        $data = $query->get()->map(function ($round) {
            return [
                'round'   => $round->only('id', 'name', 'type', 'order'),
                'matches' => $round->matches->map(function ($match) {
                    return [
                        'id'                   => $match->id,
                        'scheduled_at'         => $match->scheduled_at?->toIso8601String(),
                        'prediction_closes_at' => $match->prediction_closes_at?->toIso8601String(),
                        'venue'                => $match->venue,
                        'status'               => $match->status,
                        'is_prediction_open'   => $match->isPredictionOpen(),
                        'bracket_slot'         => $match->bracket_slot,
                        'home_team'            => $match->homeTeam ? [
                            'id'         => $match->homeTeam->id,
                            'name'       => $match->homeTeam->name,
                            'short_name' => $match->homeTeam->short_name,
                            'flag_url'   => $match->homeTeam->country?->flag_url,
                        ] : null,
                        'home_placeholder'     => $match->home_placeholder,
                        'away_team'            => $match->awayTeam ? [
                            'id'         => $match->awayTeam->id,
                            'name'       => $match->awayTeam->name,
                            'short_name' => $match->awayTeam->short_name,
                            'flag_url'   => $match->awayTeam->country?->flag_url,
                        ] : null,
                        'away_placeholder'     => $match->away_placeholder,
                        'result'               => $match->result ? [
                            'home_score'           => $match->result->home_score,
                            'away_score'           => $match->result->away_score,
                            'winner'               => $match->result->winner,
                            'home_score_penalties' => $match->result->home_score_penalties,
                            'away_score_penalties' => $match->result->away_score_penalties,
                        ] : null,
                    ];
                }),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function liveMatches(): JsonResponse
    {
        $matches = GameMatch::where('status', 'in_progress')
            ->with(['homeTeam.country', 'awayTeam.country', 'result', 'round.tournament'])
            ->get()
            ->map(fn ($match) => [
                'id'               => $match->id,
                'scheduled_at'     => $match->scheduled_at?->toIso8601String(),
                'venue'            => $match->venue,
                'status'           => $match->status,
                'tournament'       => [
                    'id'       => $match->round->tournament->id,
                    'name'     => $match->round->tournament->name,
                    'slug'     => $match->round->tournament->slug,
                    'logo_url' => $match->round->tournament->logo_url,
                ],
                'round'            => [
                    'id'   => $match->round->id,
                    'name' => $match->round->name,
                ],
                'home_team'        => $match->homeTeam ? [
                    'id'         => $match->homeTeam->id,
                    'name'       => $match->homeTeam->name,
                    'short_name' => $match->homeTeam->short_name,
                    'flag_url'   => $match->homeTeam->country?->flag_url,
                ] : null,
                'home_placeholder' => $match->home_placeholder,
                'away_team'        => $match->awayTeam ? [
                    'id'         => $match->awayTeam->id,
                    'name'       => $match->awayTeam->name,
                    'short_name' => $match->awayTeam->short_name,
                    'flag_url'   => $match->awayTeam->country?->flag_url,
                ] : null,
                'away_placeholder' => $match->away_placeholder,
                'result'           => $match->result
                    ? ['home_score' => $match->result->home_score, 'away_score' => $match->result->away_score]
                    : null,
            ]);

        return response()->json(['data' => $matches]);
    }

    public function globalStandings(string $slug): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();

        // Per-user, per-match deduplication using DISTINCT ON (PostgreSQL).
        // A user may have predictions in multiple quinielas for the same match;
        // we count each match only once (earliest prediction wins).
        $standings = DB::select(
            "SELECT
                u.id,
                u.name,
                u.avatar_url,
                COALESCE(SUM(best.points), 0)::int                              AS total_points,
                COUNT(CASE WHEN best.points = 3 THEN 1 END)::int                AS exact_scores,
                COUNT(CASE WHEN best.points = 1 THEN 1 END)::int                AS correct_results,
                COUNT(best.match_id)::int                                        AS predictions_made
            FROM users u
            JOIN (
                SELECT DISTINCT ON (p.user_id, p.match_id)
                    p.user_id,
                    p.match_id,
                    COALESCE(s.points, 0) AS points
                FROM predictions p
                JOIN matches m  ON m.id  = p.match_id
                JOIN rounds  r  ON r.id  = m.round_id
                LEFT JOIN scores s ON s.prediction_id = p.id
                WHERE r.tournament_id = ?
                ORDER BY p.user_id, p.match_id, p.id ASC
            ) best ON best.user_id = u.id
            GROUP  BY u.id, u.name, u.avatar_url
            ORDER  BY total_points DESC, exact_scores DESC, correct_results DESC
            LIMIT  10",
            [$tournament->id]
        );

        return response()->json(['data' => $standings]);
    }

    public function publicQuinielas(string $slug): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();

        $quinielas = $tournament->quinielas()
            ->where('type', 'public')
            ->where('is_active', true)
            ->withCount('participants')
            ->with('creator:id,name,avatar_url')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $quinielas->items(),
            'meta' => [
                'current_page' => $quinielas->currentPage(),
                'last_page'    => $quinielas->lastPage(),
                'total'        => $quinielas->total(),
            ],
        ]);
    }

    public function teamStandings(string $slug): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)
            ->with([
                'rounds.matches.homeTeam.country',
                'rounds.matches.awayTeam.country',
                'rounds.matches.result',
            ])
            ->firstOrFail();

        $meta        = $tournament->meta ?? [];
        $hasGroups   = $tournament->rounds->where('type', 'group')->isNotEmpty();
        $format      = $hasGroups ? 'groups' : 'table';
        $qualSpots   = $meta['qualification_spots'] ?? ($format === 'groups' ? 2 : 8);

        if ($format === 'groups') {
            $groups = $tournament->rounds
                ->where('type', 'group')
                ->map(fn ($round) => [
                    'name'  => $round->name,
                    'teams' => $this->buildTable($round->matches),
                ])
                ->values();

            return response()->json([
                'data' => [
                    'format'              => 'groups',
                    'qualification_spots' => $qualSpots,
                    'groups'              => $groups,
                ],
            ]);
        }

        $allMatches = $tournament->rounds->flatMap(fn ($r) => $r->matches);

        return response()->json([
            'data' => [
                'format'              => 'table',
                'qualification_spots' => $qualSpots,
                'teams'               => $this->buildTable($allMatches),
            ],
        ]);
    }

    private function buildTable($matches): array
    {
        $table = [];

        foreach ($matches as $match) {
            if (!$match->homeTeam || !$match->awayTeam) continue;

            $homeId = $match->homeTeam->id;
            $awayId = $match->awayTeam->id;

            if (!isset($table[$homeId])) {
                $table[$homeId] = $this->initTeamRow($match->homeTeam);
            }
            if (!isset($table[$awayId])) {
                $table[$awayId] = $this->initTeamRow($match->awayTeam);
            }

            if (!$match->result) continue;

            $hg = $match->result->home_score;
            $ag = $match->result->away_score;

            $table[$homeId]['played']++;
            $table[$awayId]['played']++;
            $table[$homeId]['goals_for']      += $hg;
            $table[$homeId]['goals_against']  += $ag;
            $table[$awayId]['goals_for']      += $ag;
            $table[$awayId]['goals_against']  += $hg;

            if ($hg > $ag) {
                $table[$homeId]['won']++;
                $table[$homeId]['points'] += 3;
                $table[$awayId]['lost']++;
            } elseif ($hg < $ag) {
                $table[$awayId]['won']++;
                $table[$awayId]['points'] += 3;
                $table[$homeId]['lost']++;
            } else {
                $table[$homeId]['drawn']++;
                $table[$homeId]['points']++;
                $table[$awayId]['drawn']++;
                $table[$awayId]['points']++;
            }
        }

        foreach ($table as &$row) {
            $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];
        }
        unset($row);

        usort($table, function ($a, $b) {
            if ($b['points']          !== $a['points'])          return $b['points']          - $a['points'];
            if ($b['goal_difference'] !== $a['goal_difference']) return $b['goal_difference'] - $a['goal_difference'];
            if ($b['goals_for']       !== $a['goals_for'])       return $b['goals_for']       - $a['goals_for'];
            return strcmp($a['name'], $b['name']);
        });

        return array_values($table);
    }

    private function initTeamRow($team): array
    {
        return [
            'id'              => $team->id,
            'name'            => $team->name,
            'short_name'      => $team->short_name,
            'logo_url'        => $team->logo_url ?? $team->country?->flag_url,
            'played'          => 0,
            'won'             => 0,
            'drawn'           => 0,
            'lost'            => 0,
            'goals_for'       => 0,
            'goals_against'   => 0,
            'goal_difference' => 0,
            'points'          => 0,
        ];
    }
}
