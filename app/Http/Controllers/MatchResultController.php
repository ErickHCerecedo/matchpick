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
        // ── DRY RUN: DB writes disabled. Only logs and returns API data. ──────
        $tournament = Tournament::where('slug', $slug)->firstOrFail();

        $pendingMatches = $tournament->matches()
            ->whereNotNull('external_id')
            ->where('scheduled_at', '<=', now())
            ->whereDoesntHave('result')
            ->get();

        $pendingSummary = $pendingMatches->map(fn ($m) => [
            'id'           => $m->id,
            'external_id'  => $m->external_id,
            'status'       => $m->status,
            'scheduled_at' => $m->scheduled_at?->toIso8601String(),
        ])->toArray();

        \Log::info('[DRY RUN] syncTournament — partidos pendientes', [
            'tournament' => $slug,
            'count'      => $pendingMatches->count(),
            'matches'    => $pendingSummary,
        ]);

        if ($pendingMatches->isEmpty()) {
            return response()->json([
                'data'    => ['dry_run' => true, 'pending_matches' => [], 'fixtures' => []],
                'message' => '[DRY RUN] No hay partidos pendientes. Sin llamada a la API.',
            ]);
        }

        $allFixtures = [];
        $errors      = [];

        foreach ($pendingMatches->chunk(20) as $chunk) {
            $ids = $chunk->pluck('external_id')->all();

            \Log::info('[DRY RUN] Llamando a la API con IDs', ['ids' => $ids]);

            try {
                $fixtures = $api->getFixturesByIds($ids);
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                \Log::error('[DRY RUN] Error en la API', ['error' => $e->getMessage()]);
                continue;
            }

            \Log::info('[DRY RUN] Respuesta cruda de la API', [
                'count'    => count($fixtures),
                'fixtures' => $fixtures,
            ]);

            foreach ($fixtures as $fixture) {
                $externalId  = (string) ($fixture['fixture']['id'] ?? '');
                $apiStatus   = $fixture['fixture']['status']['short'] ?? '?';
                $mappedStatus = $api->mapStatus($apiStatus);
                $match       = $chunk->firstWhere('external_id', $externalId);

                $entry = [
                    'external_id'    => $externalId,
                    'match_id'       => $match?->id,
                    'home_team'      => $fixture['teams']['home']['name'] ?? null,
                    'away_team'      => $fixture['teams']['away']['name'] ?? null,
                    'api_status'     => $apiStatus,
                    'mapped_status'  => $mappedStatus,
                    'home_score'     => $fixture['goals']['home'] ?? null,
                    'away_score'     => $fixture['goals']['away'] ?? null,
                    'would_sync'     => $mappedStatus === 'finished',
                ];

                \Log::info('[DRY RUN] Fixture procesado', $entry);
                $allFixtures[] = $entry;

                // ── DB WRITES DISABLED ──────────────────────────────────────
                // if ($mappedStatus !== 'finished' || !$match) { continue; }
                //
                // $result = MatchResult::create([
                //     'match_id'     => $match->id,
                //     'home_score'   => $fixture['goals']['home'] ?? 0,
                //     'away_score'   => $fixture['goals']['away'] ?? 0,
                //     'confirmed_at' => now(),
                // ]);
                // $match->update(['status' => 'finished']);
                // CalculateScoresJob::dispatch($result);
                // ───────────────────────────────────────────────────────────
            }
        }

        \Log::info('[DRY RUN] Resumen final', [
            'total_fixtures' => count($allFixtures),
            'would_sync'     => collect($allFixtures)->where('would_sync', true)->count(),
            'errors'         => $errors,
        ]);

        return response()->json([
            'data' => [
                'dry_run'         => true,
                'pending_matches' => $pendingSummary,
                'fixtures'        => $allFixtures,
                'would_sync'      => collect($allFixtures)->where('would_sync', true)->count(),
                'errors'          => $errors,
            ],
            'message' => '[DRY RUN] Ningún dato fue modificado. Revisa los logs o esta respuesta para ver el resultado.',
        ]);
    }
}
