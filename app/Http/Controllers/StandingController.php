<?php

namespace App\Http\Controllers;

use App\Http\Resources\StandingResource;
use App\Models\Quiniela;
use Illuminate\Http\JsonResponse;

class StandingController extends Controller
{
    public function index(string $slug): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();

        $standings = $quiniela->standings()
            ->with('user:id,name,avatar_url')
            ->orderByDesc('total_points')
            ->orderByDesc('exact_scores')
            ->orderByDesc('correct_results')
            ->orderByDesc('predictions_made')
            ->get()
            ->map(function ($standing, $index) {
                $standing->rank = $index + 1;
                return $standing;
            });

        return response()->json(['data' => StandingResource::collection($standings)]);
    }
}
