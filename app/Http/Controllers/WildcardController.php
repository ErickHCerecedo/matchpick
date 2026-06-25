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

    // Points by podium place: 1st=9, 2nd=6, 3rd=3
    private const POINTS_BY_PLACE = [1 => 9, 2 => 6, 3 => 3];

    private function isOpen(): bool
    {
        return now()->lt(new \DateTime(self::DEADLINE));
    }

    /** Shape a team with per-pick earned points based on the confirmed podium. */
    private function pickShape(Team $team, ?array $podium): array
    {
        $points = null;
        $place  = null;

        if ($podium !== null) {
            $allSet = !empty($podium['first']) && !empty($podium['second']) && !empty($podium['third']);

            foreach ([1 => 'first', 2 => 'second', 3 => 'third'] as $pos => $key) {
                if (isset($podium[$key]) && (int) $podium[$key] === $team->id) {
                    $place  = $pos;
                    $points = self::POINTS_BY_PLACE[$pos];
                    break;
                }
            }

            // Once all 3 positions are confirmed, unmatched teams earn 0
            if ($points === null && $allSet) {
                $points = 0;
            }
        }

        return [
            'id'         => $team->id,
            'name'       => $team->name,
            'short_name' => $team->short_name,
            'flag_url'   => $team->logo_url ?? $team->country?->flag_url,
            'points'     => $points, // null=undetermined, 0=no points, 3/6/9=earned
            'place'      => $place,  // null=not placed, 1/2/3=position
        ];
    }

    /** Basic team shape without podium info (used in save response). */
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

        if (!$quiniela->wildcard_enabled) {
            return response()->json(['message' => 'Wildcard not enabled for this quiniela.'], 404);
        }

        $user = $request->user();

        $isQuinielaAdmin = $quiniela->participants()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->exists();

        if (!$isQuinielaAdmin && !$user->is_admin) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $podium = $quiniela->tournament->meta['wildcard_podium'] ?? null;

        $wildcards = Wildcard::with(['user:id,name', 'team1.country', 'team2.country', 'team3.country'])
            ->where('quiniela_id', $quiniela->id)
            ->get()
            ->map(function ($wc) use ($podium) {
                $picks = [];
                foreach (['team1', 'team2', 'team3'] as $rel) {
                    if ($wc->$rel) $picks[] = $this->pickShape($wc->$rel, $podium);
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

        if (!$quiniela->wildcard_enabled) {
            return response()->json(['message' => 'Wildcard not enabled for this quiniela.'], 404);
        }

        $user = $request->user();

        if (!$quiniela->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Not a participant.'], 403);
        }

        $podium = $quiniela->tournament->meta['wildcard_podium'] ?? null;

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
                    $picks[] = $this->pickShape($wildcard->$rel, $podium);
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

        if (!$quiniela->wildcard_enabled) {
            return response()->json(['message' => 'Wildcard not enabled for this quiniela.'], 404);
        }
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
