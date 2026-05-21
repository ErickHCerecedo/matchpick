<?php

namespace App\Services;

use App\Models\MatchResult;
use App\Models\Prediction;

class ScoringService
{
    public function calculate(Prediction $prediction, MatchResult $result): array
    {
        if ($prediction->home_score === $result->home_score
            && $prediction->away_score === $result->away_score) {
            return ['total' => 3, 'breakdown' => ['result' => 1, 'exact' => 2]];
        }

        $predictedWinner = $this->winner($prediction->home_score, $prediction->away_score);
        if ($predictedWinner === $result->winner) {
            return ['total' => 1, 'breakdown' => ['result' => 1, 'exact' => 0]];
        }

        return ['total' => 0, 'breakdown' => ['result' => 0, 'exact' => 0]];
    }

    private function winner(int $home, int $away): string
    {
        if ($home > $away) return 'home';
        if ($away > $home) return 'away';
        return 'draw';
    }
}
