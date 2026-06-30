<?php

namespace App\Services;

use App\Models\MatchResult;
use App\Models\Prediction;

class ScoringService
{
    public function calculate(Prediction $prediction, MatchResult $result): array
    {
        $basePoints = 0;
        $breakdown  = ['result' => 0, 'exact' => 0, 'penalties' => 0];

        if ($prediction->home_score === $result->home_score
            && $prediction->away_score === $result->away_score) {
            $basePoints          = 3;
            $breakdown['result'] = 1;
            $breakdown['exact']  = 2;
        } elseif ($this->winner($prediction->home_score, $prediction->away_score) === $result->winner) {
            $basePoints          = 1;
            $breakdown['result'] = 1;
        }

        $penaltyPoints = $this->calculatePenaltyBonus($prediction, $result);
        $breakdown['penalties'] = $penaltyPoints;

        return ['total' => $basePoints + $penaltyPoints, 'breakdown' => $breakdown];
    }

    private function calculatePenaltyBonus(Prediction $prediction, MatchResult $result): int
    {
        // Only applies when the match was decided on penalties (draw after 90min with penalty scores set)
        if ($result->winner !== 'draw' || $result->home_score_penalties === null || $result->away_score_penalties === null) {
            return 0;
        }

        // Need the quiniela to know if penalties are enabled and what mode is configured
        $quiniela = $prediction->relationLoaded('quiniela') ? $prediction->quiniela : null;
        if (!$quiniela?->penalties_enabled || !$quiniela->penalties_mode) {
            return 0;
        }

        $actualWinner = $result->home_score_penalties > $result->away_score_penalties ? 'home' : 'away';

        if ($quiniela->penalties_mode === 'winner') {
            return $prediction->penalties_winner === $actualWinner ? 2 : 0;
        }

        if ($quiniela->penalties_mode === 'exact') {
            return ($prediction->penalties_home === $result->home_score_penalties
                && $prediction->penalties_away === $result->away_score_penalties)
                ? 3 : 0;
        }

        return 0;
    }

    private function winner(int $home, int $away): string
    {
        if ($home > $away) return 'home';
        if ($away > $home) return 'away';
        return 'draw';
    }
}
