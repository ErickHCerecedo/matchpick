<?php

namespace App\Http\Controllers;

use App\Http\Resources\TournamentResource;
use App\Http\Resources\MatchResource;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function index(): JsonResponse
    {
        $tournaments = Tournament::where('is_active', true)->get();
        return response()->json(['data' => TournamentResource::collection($tournaments)]);
    }

    public function show(string $slug): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->with('rounds')->firstOrFail();
        return response()->json(['data' => new TournamentResource($tournament)]);
    }

    public function matches(string $slug): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();
        $rounds = $tournament->rounds()->with([
            'matches.homeTeam.country',
            'matches.awayTeam.country',
            'matches.result',
        ])->get();

        $data = $rounds->map(fn($round) => [
            'round' => $round->only('id', 'name', 'type', 'order'),
            'matches' => MatchResource::collection($round->matches),
        ]);

        return response()->json(['data' => $data]);
    }
}
