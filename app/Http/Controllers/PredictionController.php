<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkPredictionRequest;
use App\Models\GameMatch;
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
        $match = GameMatch::findOrFail($matchId);

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

    public function participantBreakdown(Request $request, string $slug, int $userId): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();
        $authUser = $request->user();

        if (!$quiniela->participants()->where('user_id', $authUser->id)->exists()) {
            return response()->json(['message' => 'You are not a participant of this quiniela.'], 403);
        }

        if (!$quiniela->participants()->where('user_id', $userId)->exists()) {
            return response()->json(['message' => 'User is not a participant of this quiniela.'], 404);
        }

        $targetUser = \App\Models\User::findOrFail($userId);
        $standing   = $quiniela->standings()->where('user_id', $userId)->first();

        $rounds = $quiniela->tournament->rounds()->with([
            'matches' => function ($q) use ($userId, $quiniela) {
                $q->with([
                    'homeTeam.country',
                    'awayTeam.country',
                    'result',
                    'predictions' => fn($p) => $p
                        ->where('user_id', $userId)
                        ->where('quiniela_id', $quiniela->id)
                        ->with('score'),
                ])->orderBy('scheduled_at');
            },
        ])->orderBy('order')->get();

        $data = $rounds->map(fn($round) => [
            'round'   => $round->only('id', 'name', 'type', 'order'),
            'matches' => $round->matches->map(function ($match) {
                $started    = $match->scheduled_at->isPast();
                $prediction = $match->predictions->first();

                return [
                    'id'           => $match->id,
                    'scheduled_at' => $match->scheduled_at->toIso8601String(),
                    'status'       => $match->status,
                    'home_team'    => $match->homeTeam ? [
                        'id'         => $match->homeTeam->id,
                        'name'       => $match->homeTeam->name,
                        'short_name' => $match->homeTeam->short_name,
                        'flag_url'   => $match->homeTeam->country?->flag_url,
                    ] : null,
                    'away_team' => $match->awayTeam ? [
                        'id'         => $match->awayTeam->id,
                        'name'       => $match->awayTeam->name,
                        'short_name' => $match->awayTeam->short_name,
                        'flag_url'   => $match->awayTeam->country?->flag_url,
                    ] : null,
                    'result' => $match->result ? [
                        'home_score' => $match->result->home_score,
                        'away_score' => $match->result->away_score,
                        'winner'     => $match->result->winner,
                    ] : null,
                    'prediction' => ($started && $prediction) ? [
                        'home_score' => $prediction->home_score,
                        'away_score' => $prediction->away_score,
                    ] : null,
                    'score' => ($started && $prediction?->score) ? [
                        'points'    => $prediction->score->points,
                        'breakdown' => $prediction->score->breakdown,
                    ] : null,
                    'has_started' => $started,
                ];
            }),
        ]);

        return response()->json([
            'data' => [
                'user'     => [
                    'id'         => $targetUser->id,
                    'name'       => $targetUser->name,
                    'avatar_url' => $targetUser->avatar_url,
                ],
                'standing' => $standing ? [
                    'total_points'    => $standing->total_points,
                    'exact_scores'    => $standing->exact_scores,
                    'correct_results' => $standing->correct_results,
                    'predictions_made' => $standing->predictions_made,
                ] : null,
                'rounds'   => $data,
            ],
        ]);
    }
}
