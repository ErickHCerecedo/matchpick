<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Models\Quiniela;
use App\Models\Standing;
use App\Models\Team;
use App\Models\Wildcard;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $tournaments = Tournament::with('creator:id,name')
            ->withCount(['rounds', 'quinielas'])
            ->orderByDesc('is_active')
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn ($t) => $this->tournamentData($t) + [
                'rounds_count'    => $t->rounds_count,
                'quinielas_count' => $t->quinielas_count,
            ]);

        return response()->json(['data' => $tournaments]);
    }

    public function update(Request $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'is_active'   => 'sometimes|boolean',
            'starts_at'   => 'sometimes|nullable|date',
            'ends_at'     => 'sometimes|nullable|date',
            'logo_url'    => 'sometimes|nullable|string|max:500',
            'description' => 'sometimes|nullable|string|max:1000',
        ]);

        if (array_key_exists('description', $validated)) {
            $meta                  = $tournament->meta ?? [];
            $meta['description']   = $validated['description'];
            $tournament->meta      = $meta;
            unset($validated['description']);
        }

        $tournament->fill($validated)->save();
        $tournament->refresh();

        return response()->json(['data' => $this->tournamentData($tournament)]);
    }

    public function updateTeam(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'short_name'  => 'sometimes|string|max:10',
            'logo_url'    => 'sometimes|nullable|string|max:500',
            'external_id' => 'sometimes|nullable|string|max:100',
        ]);

        $team->update($validated);

        return response()->json([
            'data' => [
                'id'          => $team->id,
                'name'        => $team->name,
                'short_name'  => $team->short_name,
                'logo_url'    => $team->logo_url,
                'external_id' => $team->external_id,
            ],
        ]);
    }

    public function updateMatch(Request $request, GameMatch $match): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_at' => 'sometimes|date',
            'venue'        => 'sometimes|nullable|string|max:255',
            'home_team_id' => 'sometimes|nullable|exists:teams,id',
            'away_team_id' => 'sometimes|nullable|exists:teams,id',
            'external_id'  => 'sometimes|nullable|string|max:100',
        ]);

        if (isset($validated['scheduled_at'])) {
            $validated['prediction_closes_at'] = $validated['scheduled_at'];
        }

        $match->update($validated);
        $match->load('homeTeam.country', 'awayTeam.country');

        return response()->json([
            'data' => [
                'id'               => $match->id,
                'scheduled_at'     => $match->scheduled_at?->toIso8601String(),
                'venue'            => $match->venue,
                'external_id'      => $match->external_id,
                'home_team'        => $match->homeTeam ? [
                    'id'         => $match->homeTeam->id,
                    'name'       => $match->homeTeam->name,
                    'short_name' => $match->homeTeam->short_name,
                    'logo_url'   => $match->homeTeam->logo_url ?? $match->homeTeam->country?->flag_url,
                ] : null,
                'home_placeholder' => $match->home_placeholder,
                'away_team'        => $match->awayTeam ? [
                    'id'         => $match->awayTeam->id,
                    'name'       => $match->awayTeam->name,
                    'short_name' => $match->awayTeam->short_name,
                    'logo_url'   => $match->awayTeam->logo_url ?? $match->awayTeam->country?->flag_url,
                ] : null,
                'away_placeholder' => $match->away_placeholder,
            ],
        ]);
    }

    public function quinielas(Tournament $tournament): JsonResponse
    {
        $quinielas = Quiniela::where('tournament_id', $tournament->id)
            ->with(['creator:id,name,email', 'participants:id,name,email'])
            ->withCount('participants')
            ->orderByDesc('created_at')
            ->get();

        // Load standings for all quinielas in one query
        $quinielaIds = $quinielas->pluck('id');
        $standings   = Standing::whereIn('quiniela_id', $quinielaIds)
            ->get()
            ->groupBy('quiniela_id');

        $data = $quinielas->map(function ($q) use ($standings) {
            $qStandings = $standings->get($q->id, collect())->keyBy('user_id');

            $participants = $q->participants->map(function ($u) use ($qStandings) {
                $s = $qStandings->get($u->id);
                return [
                    'id'           => $u->id,
                    'name'         => $u->name,
                    'email'        => $u->email,
                    'role'         => $u->pivot->role,
                    'total_points' => $s?->total_points ?? 0,
                    'rank'         => $s?->rank ?? null,
                ];
            })->sortBy('rank')->values();

            return [
                'id'                 => $q->id,
                'name'               => $q->name,
                'slug'               => $q->slug,
                'type'               => $q->type,
                'participants_count' => $q->participants_count,
                'creator'            => $q->creator
                    ? ['id' => $q->creator->id, 'name' => $q->creator->name, 'email' => $q->creator->email]
                    : null,
                'participants'       => $participants,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /** GET /admin/tournaments/{tournament}/wildcard-teams */
    public function wildcardTeams(Tournament $tournament): JsonResponse
    {
        $ids   = $tournament->meta['wildcard_team_ids'] ?? [];
        $teams = Team::with('country')->whereIn('id', $ids)->get()
            ->map(fn($t) => $this->teamShape($t))->values();

        // Official tournaments: teams come from match participants (tournament_id is null on those rows).
        // Custom tournaments: teams are scoped directly via tournament_id.
        if ($tournament->is_custom) {
            $allTeams = Team::with('country')
                ->where('tournament_id', $tournament->id)
                ->get()
                ->map(fn($t) => $this->teamShape($t))->values();
        } else {
            $teamIds = \DB::table('matches')
                ->join('rounds', 'rounds.id', '=', 'matches.round_id')
                ->where('rounds.tournament_id', $tournament->id)
                ->whereNotNull('matches.home_team_id')
                ->whereNotNull('matches.away_team_id')
                ->selectRaw('DISTINCT UNNEST(ARRAY[matches.home_team_id, matches.away_team_id]) AS team_id')
                ->pluck('team_id');

            $allTeams = Team::with('country')
                ->whereIn('id', $teamIds)
                ->orderBy('name')
                ->get()
                ->map(fn($t) => $this->teamShape($t))->values();
        }

        return response()->json(['data' => [
            'eligible_teams' => $teams,
            'all_teams'      => $allTeams,
        ]]);
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

    /** PUT /admin/tournaments/{tournament}/wildcard-teams */
    public function setWildcardTeams(Request $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validate([
            'team_ids'   => 'required|array|max:64',
            'team_ids.*' => 'integer|exists:teams,id',
        ]);

        $meta = $tournament->meta ?? [];
        $meta['wildcard_team_ids'] = array_values(array_unique($validated['team_ids']));
        $tournament->update(['meta' => $meta]);

        return response()->json(['message' => 'Equipos del comodín actualizados.']);
    }

    /** PATCH /admin/quinielas/{quiniela}/wildcard-enabled */
    public function setQuinielaWildcard(Request $request, Quiniela $quiniela): JsonResponse
    {
        $validated = $request->validate(['enabled' => 'required|boolean']);
        $quiniela->update(['wildcard_enabled' => $validated['enabled']]);
        return response()->json([
            'message' => $validated['enabled'] ? 'Comodín activado.' : 'Comodín desactivado.',
            'data'    => ['wildcard_enabled' => $quiniela->wildcard_enabled],
        ]);
    }

    /** GET /admin/tournaments/{tournament}/wildcard-podium */
    public function wildcardPodium(Tournament $tournament): JsonResponse
    {
        $podium = $tournament->meta['wildcard_podium'] ?? [];
        $result = [];
        foreach (['first', 'second', 'third'] as $place) {
            $teamId = $podium[$place] ?? null;
            $team   = $teamId ? Team::with('country')->find($teamId) : null;
            $result[$place] = $team ? $this->teamShape($team) : null;
        }
        return response()->json(['data' => $result]);
    }

    /** PUT /admin/tournaments/{tournament}/wildcard-podium */
    public function setWildcardPodium(Request $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validate([
            'first'  => 'nullable|integer|exists:teams,id',
            'second' => 'nullable|integer|exists:teams,id',
            'third'  => 'nullable|integer|exists:teams,id',
        ]);

        $meta = $tournament->meta ?? [];
        $meta['wildcard_podium'] = $validated;
        $tournament->update(['meta' => $meta]);

        $this->recalculateWildcardPoints($tournament, $validated);

        return response()->json(['message' => 'Podio actualizado.', 'data' => $validated]);
    }

    private function recalculateWildcardPoints(Tournament $tournament, array $podium): void
    {
        // Build teamId → points map from podium
        $pointsMap = [];
        foreach ([1 => 'first', 2 => 'second', 3 => 'third'] as $pts => $key) {
            if (!empty($podium[$key])) {
                $pointsMap[(int) $podium[$key]] = [1 => 9, 2 => 6, 3 => 3][$pts];
            }
        }

        $quinielaIds = Quiniela::where('tournament_id', $tournament->id)->pluck('id');

        foreach (Wildcard::whereIn('quiniela_id', $quinielaIds)->get() as $wc) {
            $oldPoints = (int) ($wc->points_earned ?? 0);
            $newPoints = 0;
            foreach (['team1_id', 'team2_id', 'team3_id'] as $col) {
                if ($wc->$col && isset($pointsMap[$wc->$col])) {
                    $newPoints += $pointsMap[$wc->$col];
                }
            }
            $wc->update(['points_earned' => $newPoints]);

            $diff = $newPoints - $oldPoints;
            if ($diff !== 0) {
                $standing = Standing::firstOrCreate(
                    ['quiniela_id' => $wc->quiniela_id, 'user_id' => $wc->user_id],
                    ['total_points' => 0, 'exact_scores' => 0, 'correct_results' => 0, 'predictions_made' => 0]
                );
                $standing->increment('total_points', $diff);
            }
        }

        // Recalculate ranks for all affected quinielas
        foreach ($quinielaIds as $qid) {
            $standings = Standing::where('quiniela_id', $qid)
                ->orderByDesc('total_points')
                ->orderByDesc('exact_scores')
                ->orderByDesc('correct_results')
                ->get();
            foreach ($standings as $idx => $s) {
                $s->update(['rank' => $idx + 1]);
            }
        }
    }

    private function tournamentData(Tournament $t): array
    {
        return [
            'id'          => $t->id,
            'name'        => $t->name,
            'slug'        => $t->slug,
            'type'        => $t->type,
            'season'      => $t->season,
            'logo_url'    => $t->logo_url,
            'description' => $t->meta['description'] ?? null,
            'starts_at'   => $t->starts_at?->toDateString(),
            'ends_at'     => $t->ends_at?->toDateString(),
            'is_active'   => $t->is_active,
            'is_custom'   => $t->is_custom,
            'creator'     => $t->creator
                ? ['id' => $t->creator->id, 'name' => $t->creator->name]
                : null,
        ];
    }
}
