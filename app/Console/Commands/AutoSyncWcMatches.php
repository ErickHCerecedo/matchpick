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
 * For each WC match that has an external_id and is either:
 *   - scheduled  with scheduled_at <= now  (should have started)
 *   - in_progress                           (needs live score / finish detection)
 *
 * This command:
 *   1. Calls football-data.org once to get all currently live WC matches.
 *   2. For in_progress matches that are no longer live → fetches them
 *      individually to confirm they finished (1 extra call each).
 *   3. Transitions statuses and saves scores — dispatching scoring jobs
 *      ONLY when a match truly finishes (confirmed_at set).
 *
 * Usage:
 *   php artisan matches:wc-auto-sync
 */
class AutoSyncWcMatches extends Command
{
    protected $signature   = 'matches:wc-auto-sync';
    protected $description = 'Auto-start, live-score and auto-finish WC matches via football-data.org';

    // Keep polling for this many minutes after scheduled_at (covers extra time + penalties)
    private const POLL_WINDOW_MINUTES = 130;

    public function handle(FootballDataService $api): int
    {
        $watchlist = $this->buildWatchlist();

        if ($watchlist->isEmpty()) {
            $this->line('No matches to watch right now.');
            return self::SUCCESS;
        }

        $this->info("Watching {$watchlist->count()} match(es)...");

        // ── Step 1: one API call to get all live WC matches ──────────────
        try {
            $raw        = $api->getWorldCupMatches('IN_PLAY,PAUSED,HALFTIME');
            $liveById   = collect($raw['matches'] ?? [])->keyBy('id');
        } catch (\Throwable $e) {
            Log::error('[AutoSync] Failed to fetch live WC matches', ['error' => $e->getMessage()]);
            $this->error('API error: ' . $e->getMessage());
            return self::FAILURE;
        }

        // ── Step 2: process each watched match ───────────────────────────
        $notLive = [];

        foreach ($watchlist as $match) {
            $extId    = (string) $match->external_id;
            $apiMatch = $liveById->get((int) $extId);

            if ($apiMatch) {
                $this->applyLive($match, $apiMatch, $api);
            } else {
                // Not currently live — might have just finished or not started yet
                $notLive[] = $match;
            }
        }

        // ── Step 3: individual check for matches that left the live list ──
        // Only worth checking if they were already in_progress (or well past kick-off)
        foreach ($notLive as $match) {
            $gracePastKickoff = now()->subMinutes(15);
            $shouldHaveStarted = $match->scheduled_at->lte($gracePastKickoff);

            if ($match->status !== 'in_progress' && !$shouldHaveStarted) {
                // Still early — API may not have updated yet; wait
                continue;
            }

            try {
                $raw      = $api->getMatchById($match->external_id);
                $apiMatch = $raw['match'] ?? $raw; // endpoint returns the match directly
                $this->applyFinishedOrOther($match, $apiMatch, $api);
            } catch (\Throwable $e) {
                Log::warning('[AutoSync] Individual fetch failed', [
                    'match_id'    => $match->id,
                    'external_id' => $match->external_id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function buildWatchlist(): \Illuminate\Database\Eloquent\Collection
    {
        $cutoff = now()->subMinutes(self::POLL_WINDOW_MINUTES);

        return GameMatch::whereNotNull('external_id')
            ->where(function ($q) use ($cutoff) {
                $q->where('status', 'in_progress')
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->where('status', 'scheduled')
                         ->where('scheduled_at', '<=', now())
                         ->where('scheduled_at', '>=', $cutoff);
                  });
            })
            ->get();
    }

    private function applyLive(GameMatch $match, array $apiMatch, FootballDataService $api): void
    {
        // Transition to in_progress if needed
        if ($match->status !== 'in_progress') {
            $match->update(['status' => 'in_progress']);
            Log::info('[AutoSync] Match started', [
                'match_id'    => $match->id,
                'external_id' => $match->external_id,
            ]);
            $this->line("  [#{$match->external_id}] started → in_progress");
        }

        // Update live score (no job dispatch; confirmed_at stays null)
        $score = $api->liveScore($apiMatch);
        MatchResult::updateOrCreate(
            ['match_id' => $match->id],
            ['home_score' => $score['home'], 'away_score' => $score['away'], 'confirmed_at' => null]
        );

        $this->line("  [#{$match->external_id}] score {$score['home']}–{$score['away']} (live)");
    }

    private function applyFinishedOrOther(GameMatch $match, array $apiMatch, FootballDataService $api): void
    {
        $apiStatus = $apiMatch['status'] ?? 'UNKNOWN';
        $mapped    = $api->mapStatus($apiStatus);

        if ($mapped === 'finished' && $match->status !== 'finished') {
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
            $this->info("  [#{$match->external_id}] FINISHED {$homeScore}–{$awayScore} → scores queued");

        } elseif ($mapped === 'cancelled' && $match->status !== 'cancelled') {
            $match->update(['status' => 'cancelled']);
            Log::warning('[AutoSync] Match cancelled', ['match_id' => $match->id]);
            $this->warn("  [#{$match->external_id}] cancelled");
        }
    }
}
