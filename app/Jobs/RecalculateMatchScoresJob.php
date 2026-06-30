<?php

namespace App\Jobs;

use App\Models\MatchResult;
use App\Models\Prediction;
use App\Models\Score;
use App\Models\Standing;
use App\Services\ScoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-calculates scores and standings when a match result is corrected.
 * Unlike CalculateScoresJob (which only runs once per match), this job
 * computes the difference between the old and new points and applies
 * it incrementally so standings stay consistent.
 */
class RecalculateMatchScoresJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly MatchResult $matchResult) {}

    public function handle(ScoringService $scoringService): void
    {
        $predictions = Prediction::where('match_id', $this->matchResult->match_id)->with('quiniela')->get();

        $affectedQuinielaIds = [];

        foreach ($predictions as $prediction) {
            $newCalc   = $scoringService->calculate($prediction, $this->matchResult);
            $newPoints = $newCalc['total'];

            $existingScore = Score::where('prediction_id', $prediction->id)
                ->where('match_result_id', $this->matchResult->id)
                ->first();

            $oldPoints    = $existingScore?->points ?? 0;
            $oldBreakdown = $existingScore?->breakdown ?? ['result' => 0, 'exact' => 0];

            Score::updateOrCreate(
                ['prediction_id' => $prediction->id, 'match_result_id' => $this->matchResult->id],
                ['points' => $newPoints, 'breakdown' => $newCalc['breakdown']]
            );

            $standing = Standing::firstOrCreate([
                'quiniela_id' => $prediction->quiniela_id,
                'user_id'     => $prediction->user_id,
            ]);

            // Adjust total_points by the difference
            $diff = $newPoints - $oldPoints;
            if ($diff !== 0) {
                $standing->increment('total_points', $diff);
            }

            // Adjust exact_scores counter
            $wasExact = ($oldBreakdown['exact'] ?? 0) > 0;
            $isExact  = ($newCalc['breakdown']['exact'] ?? 0) > 0;
            if ($isExact && !$wasExact) {
                $standing->increment('exact_scores');
            } elseif (!$isExact && $wasExact) {
                $standing->decrement('exact_scores');
            }

            // Adjust correct_results counter (result correct, but not exact)
            $wasCorrect = ($oldBreakdown['result'] ?? 0) > 0 && ($oldBreakdown['exact'] ?? 0) === 0;
            $isCorrect  = ($newCalc['breakdown']['result'] ?? 0) > 0 && ($newCalc['breakdown']['exact'] ?? 0) === 0;
            if ($isCorrect && !$wasCorrect) {
                $standing->increment('correct_results');
            } elseif (!$isCorrect && $wasCorrect) {
                $standing->decrement('correct_results');
            }

            $affectedQuinielaIds[] = $prediction->quiniela_id;
        }

        foreach (array_unique($affectedQuinielaIds) as $quinielaId) {
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
