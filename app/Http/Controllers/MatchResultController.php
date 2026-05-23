<?php

namespace App\Http\Controllers;

use App\Jobs\CalculateScoresJob;
use App\Models\Match as GameMatch;
use App\Models\MatchResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchResultController extends Controller
{
    public function store(Request $request, GameMatch $match): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'home_score' => 'required|integer|min:0',
            'away_score' => 'required|integer|min:0',
        ]);

        $result = MatchResult::updateOrCreate(
            ['match_id' => $match->id],
            [
                'home_score' => $request->home_score,
                'away_score' => $request->away_score,
                'confirmed_at' => now(),
            ]
        );

        $match->update(['status' => 'finished']);

        CalculateScoresJob::dispatch($result);

        return response()->json(['data' => $result, 'message' => 'Result saved. Scores are being calculated.']);
    }
}
