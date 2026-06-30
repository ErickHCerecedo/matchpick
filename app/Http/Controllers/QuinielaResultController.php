<?php

namespace App\Http\Controllers;

use App\Jobs\CalculateScoresJob;
use App\Jobs\RecalculateMatchScoresJob;
use App\Models\GameMatch;
use App\Models\MatchResult;
use App\Models\Quiniela;
use App\Services\ApiFootballService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuinielaResultController extends Controller
{
    // ── Manual result entry ───────────────────────────────────────────────

    public function store(Request $request, string $slug, int $matchId): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();
        $this->authorizeAdmin($request, $quiniela);

        $match = GameMatch::findOrFail($matchId);

        if ($match->scheduled_at->isFuture()) {
            return response()->json(['message' => 'No puedes ingresar el resultado de un partido que aún no ha iniciado.'], 422);
        }

        $request->validate([
            'home_score'           => 'required|integer|min:0',
            'away_score'           => 'required|integer|min:0',
            'home_score_penalties' => 'nullable|integer|min:0|max:30',
            'away_score_penalties' => 'nullable|integer|min:0|max:30',
        ]);

        $hasPenalties = $request->home_score === $request->away_score
            && $request->home_score_penalties !== null
            && $request->away_score_penalties !== null;

        $existing = MatchResult::where('match_id', $matchId)->first();

        if ($existing) {
            $existing->update([
                'home_score'           => $request->home_score,
                'away_score'           => $request->away_score,
                'home_score_penalties' => $hasPenalties ? $request->home_score_penalties : null,
                'away_score_penalties' => $hasPenalties ? $request->away_score_penalties : null,
                'confirmed_at'         => now(),
            ]);
            RecalculateMatchScoresJob::dispatch($existing);
            $result = $existing;
        } else {
            $result = MatchResult::create([
                'match_id'             => $matchId,
                'home_score'           => $request->home_score,
                'away_score'           => $request->away_score,
                'home_score_penalties' => $hasPenalties ? $request->home_score_penalties : null,
                'away_score_penalties' => $hasPenalties ? $request->away_score_penalties : null,
                'confirmed_at'         => now(),
            ]);
            $match->update(['status' => 'finished']);
            CalculateScoresJob::dispatch($result);
        }

        return response()->json([
            'data'    => $result,
            'message' => 'Resultado guardado. Los puntajes se están calculando.',
        ]);
    }

    // ── Sync from API-Football ────────────────────────────────────────────

    public function syncFromApi(Request $request, string $slug, ApiFootballService $api): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();
        $this->authorizeAdmin($request, $quiniela);

        $pendingMatches = $quiniela->tournament
            ->matches()
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

    private function authorizeAdmin(Request $request, Quiniela $quiniela): void
    {
        $pivot = $quiniela->participants()->where('user_id', $request->user()->id)->first();
        if (!$pivot || $pivot->pivot->role !== 'admin') {
            abort(403, 'Solo el administrador de la quiniela puede realizar esta acción.');
        }
    }
}
