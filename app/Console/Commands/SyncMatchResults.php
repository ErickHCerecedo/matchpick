<?php

namespace App\Console\Commands;

use App\Jobs\CalculateScoresJob;
use App\Models\GameMatch;
use App\Models\MatchResult;
use App\Services\ApiFootballService;
use Illuminate\Console\Command;

/**
 * Syncs live and recent match results from API-Football.
 * Runs every 5 minutes via the scheduler during the World Cup period.
 *
 * Usage (manual):
 *   php artisan matches:sync-results
 */
class SyncMatchResults extends Command
{
    protected $signature   = 'matches:sync-results';
    protected $description = 'Pull live and recent match results from API-Football and update standings';

    // How many minutes after scheduled_at we keep polling (covers extra time, penalties)
    private const POLL_WINDOW_MINUTES = 120;

    public function handle(ApiFootballService $api): int
    {
        $matches = $this->getMatchesToSync();

        if ($matches->isEmpty()) {
            $this->info('No matches to sync right now.');
            return self::SUCCESS;
        }

        $this->info("Syncing {$matches->count()} match(es)...");

        // API-Football allows up to 20 IDs per request
        foreach ($matches->chunk(20) as $chunk) {
            $externalIds = $chunk->pluck('external_id')->all();

            try {
                $fixtures = $api->getFixturesByIds($externalIds);
            } catch (\Throwable $e) {
                $this->error('API request failed: ' . $e->getMessage());
                continue;
            }

            foreach ($fixtures as $fixture) {
                $this->processFixture($fixture, $api);
            }
        }

        $this->info('Sync complete.');
        return self::SUCCESS;
    }

    private function getMatchesToSync(): \Illuminate\Database\Eloquent\Collection
    {
        $cutoff = now()->subMinutes(self::POLL_WINDOW_MINUTES);

        return GameMatch::whereNotNull('external_id')
            ->where(function ($q) use ($cutoff) {
                // Currently live
                $q->where('status', 'in_progress')
                  // Or started recently and not yet marked finished/cancelled
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->where('status', 'scheduled')
                         ->where('scheduled_at', '<=', now())
                         ->where('scheduled_at', '>=', $cutoff);
                  });
            })
            ->get();
    }

    private function processFixture(array $fixture, ApiFootballService $api): void
    {
        $externalId = (string) $fixture['fixture']['id'];
        $match = GameMatch::where('external_id', $externalId)->first();

        if (!$match) {
            return;
        }

        $apiStatus = $fixture['fixture']['status']['short'];
        $newStatus = $api->mapStatus($apiStatus);

        // Always keep the status current
        if ($match->status !== $newStatus) {
            $match->update(['status' => $newStatus]);
            $this->line("  [{$externalId}] status: {$match->status} → {$newStatus}");
        }

        // Only record the result once (CalculateScoresJob uses increment — not idempotent)
        if ($newStatus === 'finished' && !$match->result()->exists()) {
            $homeScore = $fixture['goals']['home'] ?? 0;
            $awayScore = $fixture['goals']['away'] ?? 0;

            $result = MatchResult::create([
                'match_id'     => $match->id,
                'home_score'   => $homeScore,
                'away_score'   => $awayScore,
                'confirmed_at' => now(),
            ]);

            CalculateScoresJob::dispatch($result);

            $this->info("  [{$externalId}] result saved: {$homeScore}-{$awayScore}. Scores queued.");
        }
    }
}
