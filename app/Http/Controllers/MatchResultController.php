<?php

namespace App\Http\Controllers;

use App\Jobs\CalculateScoresJob;
use App\Jobs\RecalculateMatchScoresJob;
use App\Models\GameMatch;
use App\Models\MatchResult;
use App\Services\FootballDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchResultController extends Controller
{
    public function store(Request $request, GameMatch $match): JsonResponse
    {
        $request->validate([
            'home_score' => 'required|integer|min:0',
            'away_score' => 'required|integer|min:0',
        ]);

        $existing = MatchResult::where('match_id', $match->id)->first();

        if ($existing) {
            $existing->update([
                'home_score'   => $request->home_score,
                'away_score'   => $request->away_score,
                'confirmed_at' => now(),
            ]);
            RecalculateMatchScoresJob::dispatch($existing);
            $result = $existing;
        } else {
            $result = MatchResult::create([
                'match_id'     => $match->id,
                'home_score'   => $request->home_score,
                'away_score'   => $request->away_score,
                'confirmed_at' => now(),
            ]);
            // Only mark finished if the match wasn't already set to in_progress by admin
            if ($match->status !== 'in_progress') {
                $match->update(['status' => 'finished']);
            }
            CalculateScoresJob::dispatch($result);
        }

        return response()->json(['data' => $result, 'message' => 'Resultado guardado. Los puntajes se están calculando.']);
    }

    public function updateStatus(Request $request, GameMatch $match): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:scheduled,in_progress,finished,cancelled',
        ]);

        $match->update(['status' => $request->status]);

        return response()->json(['data' => $match, 'message' => 'Estado del partido actualizado.']);
    }

    public function testFootballData(FootballDataService $api): JsonResponse
    {
        try {
            $raw = $api->getWorldCupMatches();

            $matches = collect($raw['matches'] ?? [])->map(fn ($m) => [
                'id'          => $m['id'],
                'date'        => substr($m['utcDate'] ?? '', 0, 10),
                'time'        => substr($m['utcDate'] ?? '', 11, 5) . ' UTC',
                'status'      => $m['status'],
                'home'        => $m['homeTeam']['name'] ?? 'TBD',
                'away'        => $m['awayTeam']['name'] ?? 'TBD',
                'score_home'  => $m['score']['fullTime']['home'] ?? null,
                'score_away'  => $m['score']['fullTime']['away'] ?? null,
                'matchday'    => $m['matchday'] ?? null,
                'stage'       => $m['stage'] ?? null,
            ])->values()->all();

            return response()->json([
                'data' => [
                    'ok'           => true,
                    'total'        => $raw['resultSet']['count'] ?? count($matches),
                    'competition'  => $raw['competition']['name'] ?? null,
                    'season'       => $raw['filters']['season'] ?? null,
                    'rate_limit'   => $raw['_rate_limit'] ?? null,
                    'matches'      => $matches,
                ],
                'message' => 'Conexión exitosa a football-data.org',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'data'    => ['ok' => false],
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
