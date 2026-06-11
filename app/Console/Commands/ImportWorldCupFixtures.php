<?php

namespace App\Console\Commands;

use App\Models\GameMatch;
use App\Models\Round;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\ApiFootballService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One-time (re-runnable) import of all FIFA World Cup 2026 fixtures
 * from API-Football into the local database.
 *
 * Usage:
 *   php artisan import:world-cup-fixtures
 *   php artisan import:world-cup-fixtures --season=2026
 */
class ImportWorldCupFixtures extends Command
{
    protected $signature   = 'import:world-cup-fixtures {--season=2026}';
    protected $description = 'Import all FIFA World Cup fixtures from API-Football';

    public function handle(ApiFootballService $api): int
    {
        $season = (int) $this->option('season');

        $this->info("Fetching World Cup {$season} fixtures from API-Football...");

        $fixtures = $api->getWorldCupFixtures($season);

        if (empty($fixtures)) {
            $this->warn('No fixtures returned. Check your APIFOOTBALL_KEY and that the season exists.');
            return self::FAILURE;
        }

        $this->info('Total fixtures: ' . count($fixtures));

        $tournament = Tournament::where('slug', "world-cup-{$season}")->first();

        if (!$tournament) {
            $this->error("Tournament with slug 'world-cup-{$season}' not found. Run the TournamentSeeder first.");
            return self::FAILURE;
        }

        // ── 1. Build rounds ──────────────────────────────────────────────────
        $roundsByApiName = [];

        foreach ($fixtures as $fixture) {
            $apiRound = $fixture['league']['round'];
            if (isset($roundsByApiName[$apiRound])) {
                continue;
            }

            $type  = $api->mapRoundType($apiRound);
            $order = $api->mapRoundOrder($apiRound);

            $round = Round::firstOrCreate(
                ['tournament_id' => $tournament->id, 'name' => $apiRound],
                ['type' => $type, 'order' => $order]
            );

            $roundsByApiName[$apiRound] = $round->id;
            $this->line("  Round: {$apiRound} (type={$type}, order={$order})");
        }

        // ── 2. Build matches ─────────────────────────────────────────────────
        $created = $updated = 0;

        foreach ($fixtures as $fixture) {
            $roundId    = $roundsByApiName[$fixture['league']['round']];
            $externalId = (string) $fixture['fixture']['id'];
            $scheduledAt = $fixture['fixture']['date'];   // ISO 8601 with timezone

            $homeTeam = $this->resolveTeam($fixture['teams']['home']);
            $awayTeam = $this->resolveTeam($fixture['teams']['away']);

            $status = $api->mapStatus($fixture['fixture']['status']['short']);

            $predictionClosesAt = $scheduledAt;

            $match = GameMatch::updateOrCreate(
                ['external_id' => $externalId],
                [
                    'round_id'             => $roundId,
                    'home_team_id'         => $homeTeam?->id,
                    'away_team_id'         => $awayTeam?->id,
                    'scheduled_at'         => $scheduledAt,
                    'venue'                => $fixture['fixture']['venue']['name'] ?? null,
                    'status'               => $status,
                    'prediction_closes_at' => $predictionClosesAt,
                ]
            );

            $match->wasRecentlyCreated ? $created++ : $updated++;
        }

        $this->info("Done. Created: {$created}, Updated: {$updated}.");

        return self::SUCCESS;
    }

    /**
     * Find a team by API external_id, then fall back to name matching.
     * Stores the external_id on the team so future imports are faster.
     */
    private function resolveTeam(array $apiTeam): ?Team
    {
        $externalId = (string) $apiTeam['id'];

        // Fast path: already linked
        $team = Team::where('external_id', $externalId)->first();
        if ($team) {
            return $team;
        }

        // Slow path: match by name (case-insensitive)
        $team = Team::whereRaw('LOWER(name) = ?', [Str::lower($apiTeam['name'])])->first();

        if ($team) {
            $team->update(['external_id' => $externalId]);
            return $team;
        }

        // Create minimal team so the fixture still imports cleanly
        $this->warn("  Team not found in DB, creating: {$apiTeam['name']}");

        return Team::create([
            'external_id' => $externalId,
            'name'        => $apiTeam['name'],
            'short_name'  => Str::upper(Str::substr($apiTeam['name'], 0, 3)),
            'logo_url'    => $apiTeam['logo'] ?? null,
        ]);
    }
}
