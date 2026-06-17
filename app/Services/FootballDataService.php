<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FootballDataService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.footballdata.base_url');
        $this->token   = config('services.footballdata.token');
    }

    /**
     * Fetch all World Cup 2026 matches from football-data.org.
     */
    public function getWorldCupMatches(?string $status = null): array
    {
        $params = [];
        if ($status) {
            $params['status'] = $status;
        }

        return $this->request('/competitions/WC/matches', $params);
    }

    /**
     * Fetch a single match by its football-data.org ID.
     */
    public function getMatchById(string|int $id): array
    {
        return $this->request('/matches/' . $id);
    }

    /**
     * Map a football-data.org status to our internal status.
     * Returns null when no transition is needed (e.g. still SCHEDULED).
     */
    public function mapStatus(string $apiStatus): ?string
    {
        return match ($apiStatus) {
            'IN_PLAY', 'PAUSED', 'HALFTIME'           => 'in_progress',
            'FINISHED'                                  => 'finished',
            'POSTPONED', 'CANCELLED', 'SUSPENDED'      => 'cancelled',
            default                                     => null,
        };
    }

    /**
     * Extract the best available live score from a match object.
     * During play fullTime is null; halfTime holds the last known score.
     */
    public function liveScore(array $match): array
    {
        $ft = $match['score']['fullTime']  ?? [];
        $ht = $match['score']['halfTime']  ?? [];

        return [
            'home'   => (int) ($ft['home'] ?? $ht['home'] ?? 0),
            'away'   => (int) ($ft['away'] ?? $ht['away'] ?? 0),
            'minute' => $match['minute'] ?? null,
        ];
    }

    /**
     * Fetch currently live matches across all competitions.
     */
    public function getLiveMatches(): array
    {
        return $this->request('/matches', ['status' => 'IN_PLAY,PAUSED,HALFTIME']);
    }

    private function request(string $path, array $params = []): array
    {
        $response = Http::withHeaders([
            'X-Auth-Token' => $this->token,
        ])->get($this->baseUrl . $path, $params);

        $data = $response->json() ?? [];

        // Attach rate-limit info from response headers
        $data['_rate_limit'] = [
            'available_minute' => $response->header('X-Requests-Available-Minute'),
            'allowed_minute'   => $response->header('X-RequestCounter-Reset'),
        ];

        if (!$response->successful()) {
            $message = $data['message'] ?? "football-data.org devolvió {$response->status()}";
            throw new \RuntimeException($message);
        }

        return $data;
    }
}
