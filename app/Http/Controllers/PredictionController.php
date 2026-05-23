<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkPredictionRequest;
use App\Models\Match as GameMatch;
use App\Models\Prediction;
use App\Models\Quiniela;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictionController extends Controller
{
    public function bulkUpsert(BulkPredictionRequest $request, string $slug): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();
        $user = $request->user();

        // Verify user is participant
        if (!$quiniela->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are not a participant of this quiniela.'], 403);
        }

        $saved = [];
        $errors = [];

        foreach ($request->predictions as $item) {
            $match = GameMatch::find($item['match_id']);

            if (!$match || !$match->isPredictionOpen()) {
                $errors[] = ['match_id' => $item['match_id'], 'reason' => 'Predictions are closed for this match.'];
                continue;
            }

            $prediction = Prediction::updateOrCreate(
                ['user_id' => $user->id, 'quiniela_id' => $quiniela->id, 'match_id' => $item['match_id']],
                ['home_score' => $item['home_score'], 'away_score' => $item['away_score']]
            );

            // Update predictions_made counter in standings (only on create)
            if ($prediction->wasRecentlyCreated) {
                \App\Models\Standing::where('quiniela_id', $quiniela->id)
                    ->where('user_id', $user->id)
                    ->increment('predictions_made');
            }

            $saved[] = $prediction->id;
        }

        return response()->json([
            'data' => ['saved' => count($saved), 'errors' => $errors],
            'message' => count($saved) . ' predictions saved.',
        ]);
    }

    public function matchPredictions(Request $request, string $slug, int $matchId): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();
        $match = GameGameMatch::findOrFail($matchId);

        // Only show after match has started
        if ($match->scheduled_at->isFuture()) {
            return response()->json(['message' => 'Predictions are hidden until the match starts.'], 403);
        }

        $predictions = Prediction::where('quiniela_id', $quiniela->id)
            ->where('match_id', $matchId)
            ->with(['user:id,name,avatar_url', 'score'])
            ->get();

        return response()->json(['data' => $predictions]);
    }
}
