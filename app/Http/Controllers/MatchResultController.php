<?php

namespace App\Http\Controllers;

use App\Jobs\CalculateScoresJob;
use App\Jobs\RecalculateMatchScoresJob;
use App\Models\GameMatch;
use App\Models\MatchResult;
use App\Services\FootballDataService;
use App\Services\XlsxWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;

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
            'status' => 'required|in:scheduled,in_progress,finished,cancelled,postponed',
        ]);

        $match->update(['status' => $request->status]);

        return response()->json(['data' => $match, 'message' => 'Estado del partido actualizado.']);
    }

    public function exportWorldCupData(FootballDataService $api): Response
    {
        $raw     = $api->getWorldCupMatches();
        $matches = $raw['matches'] ?? [];

        // Collect unique teams keyed by API id
        $teams = [];
        foreach ($matches as $m) {
            foreach (['homeTeam', 'awayTeam'] as $side) {
                $t  = $m[$side] ?? [];
                $id = $t['id'] ?? null;
                if ($id && !isset($teams[$id])) {
                    $teams[$id] = [$id, $t['name'] ?? '', $t['shortName'] ?? '', $t['tla'] ?? '', $t['crest'] ?? ''];
                }
            }
        }
        usort($teams, fn($a, $b) => strcmp($a[3], $b[3])); // sort by tla

        $matchRows = array_map(fn($m) => [
            $m['id'],
            substr($m['utcDate'] ?? '', 0, 10),
            substr($m['utcDate'] ?? '', 11, 5) . ' UTC',
            $m['status']                        ?? '',
            $m['stage']                         ?? '',
            $m['matchday']                      ?? '',
            $m['homeTeam']['tla']               ?? '',
            $m['homeTeam']['name']              ?? '',
            $m['awayTeam']['tla']               ?? '',
            $m['awayTeam']['name']              ?? '',
            $m['score']['fullTime']['home']      ?? '',
            $m['score']['fullTime']['away']      ?? '',
        ], $matches);

        $xlsx = new XlsxWriter();
        $xlsx->addSheet('Equipos',  ['api_id', 'name', 'short_name', 'tla', 'crest'], array_values($teams));
        $xlsx->addSheet('Partidos', ['api_id', 'date', 'time_utc', 'status', 'stage', 'matchday', 'home_tla', 'home_name', 'away_tla', 'away_name', 'score_home', 'score_away'], $matchRows);

        $filename = 'mundial-2026-' . date('Y-m-d') . '.xlsx';
        $binary   = $xlsx->toBinary();

        return response($binary, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => strlen($binary),
        ]);
    }

    public function runSync(): JsonResponse
    {
        $started = microtime(true);

        try {
            $output   = new BufferedOutput();
            $exitCode = Artisan::call('matches:wc-auto-sync', [], $output);
            $raw      = $output->fetch();
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [
                    'ok'       => false,
                    'exit_code' => 1,
                    'lines'    => [['text' => 'Error al ejecutar el comando: ' . $e->getMessage(), 'level' => 'error']],
                    'ran_at'   => now()->toISOString(),
                    'duration' => round((microtime(true) - $started) * 1000),
                ],
            ]);
        }

        // Strip ANSI colour codes emitted by the console
        $clean = preg_replace('/\033\[[0-9;]*m/', '', $raw);
        $lines = array_values(array_filter(
            explode("\n", $clean),
            fn ($l) => trim($l) !== ''
        ));

        $parsed = array_map(function ($text) {
            $level = 'info';
            $t = strtolower($text);
            if (str_contains($t, 'error') || str_contains($t, 'fail'))  $level = 'error';
            elseif (str_contains($t, 'warn') || str_contains($t, 'not found')) $level = 'warn';
            elseif (str_contains($t, 'done') || str_contains($t, 'finished') || str_contains($t, 'started')) $level = 'success';
            elseif (str_starts_with(ltrim($text), '[#'))                $level = 'detail';
            return ['text' => $text, 'level' => $level];
        }, $lines);

        return response()->json([
            'data' => [
                'ok'       => $exitCode === 0,
                'exit_code' => $exitCode,
                'lines'    => $parsed,
                'ran_at'   => now()->toISOString(),
                'duration' => round((microtime(true) - $started) * 1000),
            ],
        ]);
    }

    public function apiMatchStatus(FootballDataService $api): JsonResponse
    {
        try {
            $dbMatches = GameMatch::whereNotNull('external_id')
                ->with(['homeTeam', 'awayTeam', 'result'])
                ->orderBy('scheduled_at')
                ->get()
                ->map(fn ($m) => [
                    'id'           => $m->id,
                    'external_id'  => $m->external_id,
                    'scheduled_at' => $m->scheduled_at,
                    'db_status'    => $m->status,
                    'home_team'    => $m->homeTeam?->name ?? 'TBD',
                    'away_team'    => $m->awayTeam?->name ?? 'TBD',
                    'db_score'     => $m->result ? [
                        'home'      => $m->result->home_score,
                        'away'      => $m->result->away_score,
                        'confirmed' => !is_null($m->result->confirmed_at),
                    ] : null,
                ]);

            if ($dbMatches->isEmpty()) {
                return response()->json([
                    'data'    => ['ok' => true, 'connected' => false, 'matches' => []],
                    'message' => 'No hay partidos con external_id asignado. Asigna external_id desde el panel de administración.',
                ]);
            }

            $raw     = $api->getWorldCupMatches();
            $allById = collect($raw['matches'] ?? [])->keyBy('id');

            $merged = $dbMatches->map(function ($m) use ($allById, $api) {
                $apiMatch  = $allById->get((int) $m['external_id']);
                $apiStatus = $apiMatch ? ($apiMatch['status'] ?? 'UNKNOWN') : null;

                return array_merge($m, [
                    'api_found'  => $apiMatch !== null,
                    'api_status' => $apiStatus,
                    'api_mapped' => $apiMatch ? $api->mapStatus($apiStatus) : null,
                    'api_score'  => $apiMatch ? $api->liveScore($apiMatch) : null,
                ]);
            });

            return response()->json([
                'data' => [
                    'ok'          => true,
                    'connected'   => true,
                    'competition' => $raw['competition']['name'] ?? null,
                    'rate_limit'  => $raw['_rate_limit'] ?? null,
                    'total_api'   => $raw['resultSet']['count'] ?? 0,
                    'matches'     => $merged->values()->all(),
                ],
                'message' => 'Conexión exitosa a football-data.org',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'data'    => ['ok' => false, 'connected' => false, 'matches' => []],
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
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
