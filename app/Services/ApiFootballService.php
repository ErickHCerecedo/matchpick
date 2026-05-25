<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ApiFootballService
{
    private string $baseUrl;
    private string $apiKey;

    // Status codes → our internal status
    private const STATUS_MAP = [
        'NS'   => 'scheduled',
        'TBD'  => 'scheduled',
        'PST'  => 'scheduled',
        'SUSP' => 'scheduled',
        '1H'   => 'in_progress',
        'HT'   => 'in_progress',
        '2H'   => 'in_progress',
        'ET'   => 'in_progress',
        'BT'   => 'in_progress',
        'P'    => 'in_progress',
        'LIVE' => 'in_progress',
        'INT'  => 'in_progress',
        'FT'   => 'finished',
        'AET'  => 'finished',
        'PEN'  => 'finished',
        'CANC' => 'cancelled',
        'ABD'  => 'cancelled',
        'AWD'  => 'cancelled',
        'WO'   => 'cancelled',
    ];

    // API round names → our round type enum
    private const ROUND_TYPE_MAP = [
        'Group Stage'   => 'group',
        'Round of 32'   => 'round_of_32',
        'Round of 16'   => 'round_of_16',
        'Quarter-finals' => 'quarter',
        'Semi-finals'   => 'semi',
        '3rd Place Final' => 'third_place',
        'Final'         => 'final',
    ];

    public function __construct()
    {
        $this->baseUrl = config('services.apifootball.base_url');
        $this->apiKey  = config('services.apifootball.key');
    }

    /**
     * Fetch all fixtures for the FIFA World Cup (league 1).
     */
    public function getWorldCupFixtures(int $season = 2026): array
    {
        return $this->request('/fixtures', ['league' => 1, 'season' => $season]);
    }

    /**
     * Fetch current state for a set of fixture IDs (max 20 per call).
     */
    public function getFixturesByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->request('/fixtures', ['ids' => implode('-', $ids)]);
    }

    /**
     * Map an API status string to our internal status.
     */
    public function mapStatus(string $apiStatus): string
    {
        return self::STATUS_MAP[$apiStatus] ?? 'scheduled';
    }

    /**
     * Map an API round name to our round type enum value.
     * "Group Stage - 1", "Group Stage - 2", etc. all map to 'group'.
     */
    public function mapRoundType(string $apiRound): string
    {
        foreach (self::ROUND_TYPE_MAP as $prefix => $type) {
            if (str_starts_with($apiRound, $prefix)) {
                return $type;
            }
        }

        return 'group';
    }

    /**
     * Derive a display order for a round name so phases sort correctly.
     * Group Stage matchdays keep individual order (1, 2, 3); knockout phases follow.
     */
    public function mapRoundOrder(string $apiRound): int
    {
        if (str_starts_with($apiRound, 'Group Stage')) {
            // "Group Stage - 1" → extract the number
            preg_match('/(\d+)$/', $apiRound, $m);
            return (int) ($m[1] ?? 1);
        }

        $knockoutOrder = [
            'Round of 32'    => 4,
            'Round of 16'    => 5,
            'Quarter-finals' => 6,
            'Semi-finals'    => 7,
            '3rd Place Final' => 8,
            'Final'          => 9,
        ];

        foreach ($knockoutOrder as $prefix => $order) {
            if (str_starts_with($apiRound, $prefix)) {
                return $order;
            }
        }

        return 99;
    }

    private function request(string $path, array $params = []): array
    {
        $response = Http::withHeaders([
            'x-apisports-key' => $this->apiKey,
            'Accept'          => 'application/json',
        ])->get($this->baseUrl . $path, $params);

        if (!$response->successful()) {
            throw new \RuntimeException(
                "API-Football returned {$response->status()} for {$path}"
            );
        }

        $body = $response->json();

        $errors = $body['errors'] ?? [];
        if (!empty($errors)) {
            throw new \RuntimeException(
                'API-Football error: ' . json_encode($errors)
            );
        }

        return $body['response'] ?? [];
    }
}
