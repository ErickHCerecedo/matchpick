<?php

namespace App\Http\Controllers;

use App\Jobs\CalculateScoresJob;
use App\Jobs\RecalculateMatchScoresJob;
use App\Models\GameMatch;
use App\Models\MatchResult;
use App\Models\Tournament;
use App\Services\ApiFootballService;
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

    public function syncTournament(Request $request, string $slug, ApiFootballService $api): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();

        $pendingMatches = $tournament->matches()
            ->whereNotNull('external_id')
            ->where('scheduled_at', '<=', now())
            ->whereDoesntHave('result')
            ->get();

        if ($pendingMatches->isEmpty()) {
            return response()->json([
                'data'    => ['synced' => 0],
                'message' => 'No hay partidos pendientes de resultado para sincronizar.',
            ]);
        }

        $synced = 0;
        $errors = [];

        foreach ($pendingMatches->chunk(20) as $chunk) {
            try {
                $fixtures = $api->getFixturesByIds($chunk->pluck('external_id')->all());
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                continue;
            }

            foreach ($fixtures as $fixture) {
                $status = $api->mapStatus($fixture['fixture']['status']['short']);

                if ($status !== 'finished') {
                    continue;
                }

                $match = $chunk->firstWhere('external_id', (string) $fixture['fixture']['id']);
                if (!$match) {
                    continue;
                }

                $result = MatchResult::create([
                    'match_id'     => $match->id,
                    'home_score'   => $fixture['goals']['home'] ?? 0,
                    'away_score'   => $fixture['goals']['away'] ?? 0,
                    'confirmed_at' => now(),
                ]);

                $match->update(['status' => 'finished']);
                CalculateScoresJob::dispatch($result);
                $synced++;
            }
        }

        $message = $synced > 0
            ? "{$synced} resultado(s) sincronizado(s). Los puntajes se están calculando."
            : 'Los partidos aún no han terminado según la API.';

        if (!empty($errors)) {
            $message .= ' Advertencia: ' . implode('; ', $errors);
        }

        return response()->json([
            'data'    => ['synced' => $synced],
            'message' => $message,
        ]);
    }
}
