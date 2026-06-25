<?php

namespace App\Http\Controllers;

use App\Models\Quiniela;
use App\Models\Team;
use App\Models\Wildcard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WildcardController extends Controller
{
    // June 28 2026 13:00 Mexico Central (UTC-6) = 19:00 UTC
    private const DEADLINE = '2026-06-28T19:00:00Z';

    private function isOpen(): bool
    {
        return now()->lt(new \DateTime(self::DEADLINE));
    }

    private function teamShape(Team $team): array
    {
        return [
            'id'         => $team->id,
            'name'       => $team->name,
            'short_name' => $team->short_name,
            'flag_url'   => $team->logo_url ?? $team->country?->flag_url,
        ];
    }

    /** GET /quinielas/{slug}/wildcards — quiniela admin or super admin */
    public function index(Request $request, string $slug): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();
        $user     = $request->user();

        $isQuinielaAdmin = $quiniela->participants()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->exists();

        if (!$isQuinielaAdmin && !$user->is_admin) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $wildcards = Wildcard::with(['user:id,name', 'team1.country', 'team2.country', 'team3.country'])
            ->where('quiniela_id', $quiniela->id)
            ->get()
            ->map(function ($wc) {
                $picks = [];
                foreach (['team1', 'team2', 'team3'] as $rel) {
                    if ($wc->$rel) $picks[] = $this->teamShape($wc->$rel);
                }
                return [
                    'user_id'       => $wc->user_id,
                    'user_name'     => $wc->user->name,
                    'picks'         => $picks,
                    'points_earned' => $wc->points_earned,
                ];
            });

        return response()->json(['data' => $wildcards]);
    }

    /** GET /quinielas/{slug}/wildcard */
    public function show(Request $request, string $slug): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();
        $user     = $request->user();

        if (!$quiniela->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Not a participant.'], 403);
        }

        // Eligible teams from tournament meta
        $eligibleTeamIds = $quiniela->tournament->meta['wildcard_team_ids'] ?? [];
        $eligibleTeams   = Team::with('country')
            ->whereIn('id', $eligibleTeamIds)
            ->get()
            ->map(fn($t) => $this->teamShape($t))
            ->values();

        $wildcard = Wildcard::with(['team1.country', 'team2.country', 'team3.country'])
            ->where('user_id', $user->id)
            ->where('quiniela_id', $quiniela->id)
            ->first();

        $picks = [];
        if ($wildcard) {
            foreach (['team1', 'team2', 'team3'] as $rel) {
                if ($wildcard->$rel) {
                    $picks[] = $this->teamShape($wildcard->$rel);
                }
            }
        }

        return response()->json([
            'data' => [
                'is_open'        => $this->isOpen(),
                'deadline'       => self::DEADLINE,
                'eligible_teams' => $eligibleTeams,
                'picks'          => $picks,
                'points_earned'  => $wildcard?->points_earned,
            ],
        ]);
    }

    /** POST /quinielas/{slug}/wildcard */
    public function save(Request $request, string $slug): JsonResponse
    {
        if (!$this->isOpen()) {
            return response()->json(['message' => 'El plazo del comodín ha cerrado.'], 403);
        }

        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();
        $user     = $request->user();

        if (!$quiniela->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Not a participant.'], 403);
        }

        $validated = $request->validate([
            'team_ids'   => 'required|array|min:1|max:3',
            'team_ids.*' => 'integer|exists:teams,id',
        ]);

        $teamIds = array_unique($validated['team_ids']);

        // Verify all picks are from the eligible list
        $eligibleIds = $quiniela->tournament->meta['wildcard_team_ids'] ?? [];
        foreach ($teamIds as $id) {
            if (!in_array($id, $eligibleIds)) {
                return response()->json(['message' => 'Equipo no elegible para el comodín.'], 422);
            }
        }

        $data = [
            'team1_id' => $teamIds[0] ?? null,
            'team2_id' => $teamIds[1] ?? null,
            'team3_id' => $teamIds[2] ?? null,
        ];

        $wildcard = Wildcard::updateOrCreate(
            ['user_id' => $user->id, 'quiniela_id' => $quiniela->id],
            $data
        );

        $wildcard->load('team1.country', 'team2.country', 'team3.country');

        $picks = [];
        foreach (['team1', 'team2', 'team3'] as $rel) {
            if ($wildcard->$rel) {
                $picks[] = $this->teamShape($wildcard->$rel);
            }
        }

        return response()->json([
            'data'    => ['picks' => $picks],
            'message' => 'Comodín guardado.',
        ]);
    }
}
