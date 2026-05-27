<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $tournaments = Tournament::with('creator:id,name')
            ->withCount(['rounds', 'quinielas'])
            ->orderByDesc('is_active')
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'name'       => $t->name,
                'slug'       => $t->slug,
                'type'       => $t->type,
                'season'     => $t->season,
                'logo_url'   => $t->logo_url,
                'starts_at'  => $t->starts_at?->toDateString(),
                'ends_at'    => $t->ends_at?->toDateString(),
                'is_active'  => $t->is_active,
                'is_custom'  => $t->is_custom,
                'creator'    => $t->creator ? ['id' => $t->creator->id, 'name' => $t->creator->name] : null,
                'rounds_count'   => $t->rounds_count,
                'quinielas_count' => $t->quinielas_count,
            ]);

        return response()->json(['data' => $tournaments]);
    }

    public function update(Request $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'starts_at' => 'sometimes|nullable|date',
            'ends_at'   => 'sometimes|nullable|date',
        ]);

        $tournament->update($validated);
        $tournament->refresh();

        return response()->json([
            'data' => [
                'id'        => $tournament->id,
                'name'      => $tournament->name,
                'slug'      => $tournament->slug,
                'type'      => $tournament->type,
                'season'    => $tournament->season,
                'logo_url'  => $tournament->logo_url,
                'starts_at' => $tournament->starts_at?->toDateString(),
                'ends_at'   => $tournament->ends_at?->toDateString(),
                'is_active' => $tournament->is_active,
                'is_custom' => $tournament->is_custom,
                'creator'   => $tournament->creator
                    ? ['id' => $tournament->creator->id, 'name' => $tournament->creator->name]
                    : null,
            ],
        ]);
    }
}
