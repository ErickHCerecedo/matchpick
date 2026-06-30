<?php

namespace App\Jobs;

use App\Models\MatchResult;
use App\Models\Prediction;
use App\Models\Score;
use App\Models\Standing;
use App\Services\ScoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CalculateScoresJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly MatchResult $matchResult) {}

    public function handle(ScoringService $scoringService): void
    {
        $predictions = Prediction::where('match_id', $this->matchResult->match_id)->with('quiniela')->get();

        foreach ($predictions as $prediction) {
            $result = $scoringService->calculate($prediction, $this->matchResult);

            Score::updateOrCreate(
                ['prediction_id' => $prediction->id, 'match_result_id' => $this->matchResult->id],
                ['points' => $result['total'], 'breakdown' => $result['breakdown']]
            );

            $standing = Standing::firstOrCreate(
                ['quiniela_id' => $prediction->quiniela_id, 'user_id' => $prediction->user_id]
            );

            $standing->increment('total_points', $result['total']);

            if ($result['breakdown']['exact'] > 0) {
                $standing->increment('exact_scores');
            } elseif ($result['breakdown']['result'] > 0) {
                $standing->increment('correct_results');
            }
        }

        // Recalculate ranks for affected quinielas
        $quinielaIds = $predictions->pluck('quiniela_id')->unique();
        foreach ($quinielaIds as $quinielaId) {
            $this->recalculateRanks($quinielaId);
        }
    }

    private function recalculateRanks(int $quinielaId): void
    {
        $standings = Standing::where('quiniela_id', $quinielaId)
            ->orderByDesc('total_points')
            ->orderByDesc('exact_scores')
            ->orderByDesc('correct_results')
            ->get();

        foreach ($standings as $index => $standing) {
            $standing->update(['rank' => $index + 1]);
        }
    }
}
