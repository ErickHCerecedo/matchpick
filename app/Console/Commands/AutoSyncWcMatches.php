<?php

namespace App\Console\Commands;

use App\Jobs\CalculateScoresJob;
use App\Jobs\RecalculateMatchScoresJob;
use App\Models\GameMatch;
use App\Models\MatchResult;
use App\Services\FootballDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Runs every minute via the Laravel scheduler.
 *
 * Strategy:
 *   1. Build a watchlist: matches with external_id that are scheduled (past
 *      kick-off) or in_progress.
 *   2. If the watchlist is empty → exit immediately (0 API calls).
 *   3. Fetch ALL WC matches from football-data.org in ONE call.
 *   4. For each watched match:
 *        - If scheduled AND scheduled_at has passed → start immediately
 *          (do NOT wait for the API to report IN_PLAY, which can lag 5-15 min)
 *        - If API reports FINISHED → save confirmed result + dispatch scoring job
 *        - If API reports CANCELLED → mark cancelled
 *        - If in_progress → update live score from API
 *
 * Usage:
 *   php artisan matches:wc-auto-sync
 */
class AutoSyncWcMatches extends Command
{
    protected $signature   = 'matches:wc-auto-sync';
    protected $description = 'Auto-start, live-score and auto-finish WC matches via football-data.org';

    private const POLL_WINDOW_MINUTES = 130;

    public function handle(FootballDataService $api): int
    {
        $now       = now();
        $watchlist = $this->buildWatchlist();

        $this->line("[AutoSync] Server time: {$now->toIso8601String()} (UTC)");

        if ($watchlist->isEmpty()) {
            $this->line('[AutoSync] Nothing to watch.');
            return self::SUCCESS;
        }

        $this->info("[AutoSync] Watching {$watchlist->count()} match(es)...");

        try {
            $raw     = $api->getWorldCupMatches(); // all 104 matches, no status filter
            $allById = collect($raw['matches'] ?? [])->keyBy('id');
        } catch (\Throwable $e) {
            Log::error('[AutoSync] Failed to fetch WC matches', ['error' => $e->getMessage()]);
            $this->error('[AutoSync] API error: ' . $e->getMessage());
            return self::FAILURE;
        }

        foreach ($watchlist as $match) {
            $apiMatch = $allById->get((int) $match->external_id);

            if (!$apiMatch) {
                Log::warning('[AutoSync] external_id not found in API', [
                    'match_id'    => $match->id,
                    'external_id' => $match->external_id,
                ]);
                $this->warn("  [#{$match->external_id}] not found in API response");
                continue;
            }

            $apiStatus  = $apiMatch['status'] ?? 'UNKNOWN';
            $mapped     = $api->mapStatus($apiStatus);
            $kickoff    = $match->scheduled_at
                ? \Carbon\Carbon::parse($match->scheduled_at)->toIso8601String()
                : '?';

            $this->line("  [#{$match->external_id}] kickoff: {$kickoff} · API status: {$apiStatus}");

            // Start by scheduled time — don't wait for the API to say IN_PLAY
            if ($match->status === 'scheduled') {
                $this->applyLive($match, $apiMatch, $api);
                continue;
            }

            // Match is already in_progress — check API for finish/cancel/postpone/suspend/score update
            match ($mapped) {
                'finished'   => $this->applyFinished($match, $apiMatch),
                'cancelled'  => $this->applyCancelled($match),
                'postponed'  => $this->applySimpleStatus($match, 'postponed', 'postponed'),
                'suspended'  => $this->applySimpleStatus($match, 'suspended', 'suspended'),
                'paused'     => $this->applyLive($match, $apiMatch, $api), // halftime: keep in_progress, update score
                default      => $this->applyLive($match, $apiMatch, $api),
            };
        }

        $this->info('[AutoSync] Done.');
        return self::SUCCESS;
    }

    private function buildWatchlist(): \Illuminate\Database\Eloquent\Collection
    {
        $cutoff = now()->subMinutes(self::POLL_WINDOW_MINUTES);

        return GameMatch::whereNotNull('external_id')
            ->where(function ($q) use ($cutoff) {
                $q->where('status', 'in_progress')
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereIn('status', ['scheduled', 'postponed'])
                         ->where('scheduled_at', '<=', now())
                         ->where('scheduled_at', '>=', $cutoff);
                  });
            })
            ->get();
    }

    private function applyLive(GameMatch $match, array $apiMatch, FootballDataService $api): void
    {
        if ($match->status !== 'in_progress') {
            $match->update(['status' => 'in_progress']);
            Log::info('[AutoSync] Match started', [
                'match_id'    => $match->id,
                'external_id' => $match->external_id,
            ]);
            $this->info("  [#{$match->external_id}] → in_progress");
        }

        $score = $api->liveScore($apiMatch);

        MatchResult::updateOrCreate(
            ['match_id'    => $match->id],
            ['home_score'  => $score['home'], 'away_score' => $score['away'], 'confirmed_at' => null]
        );

        $this->line("  [#{$match->external_id}] marcador {$score['home']}–{$score['away']} (live)");
    }

    private function applyFinished(GameMatch $match, array $apiMatch): void
    {
        if ($match->status === 'finished') {
            return;
        }

        $homeScore = (int) ($apiMatch['score']['fullTime']['home'] ?? 0);
        $awayScore = (int) ($apiMatch['score']['fullTime']['away'] ?? 0);

        $existing = MatchResult::where('match_id', $match->id)->first();

        if ($existing) {
            $existing->update([
                'home_score'   => $homeScore,
                'away_score'   => $awayScore,
                'confirmed_at' => now(),
            ]);
            RecalculateMatchScoresJob::dispatch($existing);
        } else {
            $result = MatchResult::create([
                'match_id'     => $match->id,
                'home_score'   => $homeScore,
                'away_score'   => $awayScore,
                'confirmed_at' => now(),
            ]);
            CalculateScoresJob::dispatch($result);
        }

        $match->update(['status' => 'finished']);

        Log::info('[AutoSync] Match finished', [
            'match_id'    => $match->id,
            'external_id' => $match->external_id,
            'score'       => "{$homeScore}-{$awayScore}",
        ]);
        $this->info("  [#{$match->external_id}] FINISHED {$homeScore}–{$awayScore} → scoring queued");
    }

    private function applyCancelled(GameMatch $match): void
    {
        if ($match->status === 'cancelled') {
            return;
        }

        $match->update(['status' => 'cancelled']);
        Log::warning('[AutoSync] Match cancelled', ['match_id' => $match->id]);
        $this->warn("  [#{$match->external_id}] cancelled");
    }

    private function applySimpleStatus(GameMatch $match, string $status, string $logKey): void
    {
        if ($match->status === $status) return;

        $match->update(['status' => $status]);
        Log::warning("[AutoSync] Match {$logKey}", ['match_id' => $match->id]);
        $this->warn("  [#{$match->external_id}] → {$status}");
    }
}
