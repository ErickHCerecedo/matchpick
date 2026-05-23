<?php

namespace App\Http\Controllers;

use App\Http\Resources\TournamentResource;
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
        try {
            $tournament = Tournament::where('slug', $slug)->firstOrFail();
            \Log::info('[matches] tournament found', ['id' => $tournament->id]);

            $query = $tournament->rounds()->with([
                'matches.homeTeam.country',
                'matches.awayTeam.country',
                'matches.result',
            ]);

            if ($request->has('round_type')) {
                $query->where('type', $request->round_type);
            }

            $rounds = $query->get();
            \Log::info('[matches] rounds loaded', ['count' => $rounds->count()]);

            $data = $rounds->map(function ($round) {
                \Log::info('[matches] processing round', ['round' => $round->name, 'matches' => $round->matches->count()]);

                return [
                    'round'   => $round->only('id', 'name', 'type', 'order'),
                    'matches' => $round->matches->map(function ($match) {
                        \Log::info('[matches] processing match', ['id' => $match->id]);

                        return [
                            'id'                   => $match->id,
                            'scheduled_at'         => $match->scheduled_at?->toIso8601String(),
                            'prediction_closes_at' => $match->prediction_closes_at?->toIso8601String(),
                            'venue'                => $match->venue,
                            'status'               => $match->status,
                            'is_prediction_open'   => $match->isPredictionOpen(),
                            'home_team'            => $match->homeTeam ? [
                                'id'         => $match->homeTeam->id,
                                'name'       => $match->homeTeam->name,
                                'short_name' => $match->homeTeam->short_name,
                                'flag_url'   => $match->homeTeam->country?->flag_url,
                            ] : null,
                            'away_team'            => $match->awayTeam ? [
                                'id'         => $match->awayTeam->id,
                                'name'       => $match->awayTeam->name,
                                'short_name' => $match->awayTeam->short_name,
                                'flag_url'   => $match->awayTeam->country?->flag_url,
                            ] : null,
                            'result'               => $match->result ? [
                                'home_score' => $match->result->home_score,
                                'away_score' => $match->result->away_score,
                                'winner'     => $match->result->winner,
                            ] : null,
                        ];
                    }),
                ];
            });

            \Log::info('[matches] data built successfully');
            return response()->json(['data' => $data]);

        } catch (\Throwable $e) {
            \Log::error('[matches] ERROR: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
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
}
