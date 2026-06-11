<?php

namespace App\Http\Controllers;

use App\Http\Resources\TournamentResource;
use App\Jobs\CalculateScoresJob;
use App\Jobs\RecalculateMatchScoresJob;
use App\Models\GameMatch;
use App\Models\MatchResult;
use App\Models\Round;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomTournamentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'       => 'required|string|max:150',
            'season'     => 'required|string|max:10',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date|after_or_equal:starts_at',
        ]);

        $slug = Str::slug($request->name) . '-' . Str::random(6);

        $tournament = Tournament::create([
            'name'       => $request->name,
            'slug'       => $slug,
            'type'       => 'league',
            'season'     => $request->season,
            'starts_at'  => $request->starts_at,
            'ends_at'    => $request->ends_at,
            'is_active'  => true,
            'is_custom'  => true,
            'creator_id' => $request->user()->id,
        ]);

        // Auto-create first round
        Round::create([
            'tournament_id' => $tournament->id,
            'name'          => 'Jornada 1',
            'type'          => 'general',
            'order'         => 1,
        ]);

        return response()->json([
            'data'    => new TournamentResource($tournament->load('creator')),
            'message' => 'Torneo creado exitosamente.',
        ], 201);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();
        $this->authorizeCreator($request, $tournament);

        $data = $request->validate([
            'name'     => 'sometimes|string|max:150',
            'logo_url' => 'nullable|string|max:500',
        ]);

        if (isset($data['name']) && $data['name'] !== $tournament->name) {
            $tournament->name = $data['name'];
        }
        if (array_key_exists('logo_url', $data)) {
            $tournament->logo_url = $data['logo_url'];
        }
        $tournament->save();

        return response()->json([
            'data'    => new TournamentResource($tournament->load('creator')),
            'message' => 'Torneo actualizado.',
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $tournaments = Tournament::where('creator_id', $request->user()->id)
            ->with('creator')
            ->latest()
            ->get();

        return response()->json(['data' => TournamentResource::collection($tournaments)]);
    }

    // ── Teams ─────────────────────────────────────────────────────────────

    public function teams(string $slug): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();

        if ($tournament->is_custom) {
            $teams = Team::where('tournament_id', $tournament->id)
                ->orderBy('name')
                ->get()
                ->map(fn($t) => [
                    'id'         => $t->id,
                    'name'       => $t->name,
                    'short_name' => $t->short_name,
                    'logo_url'   => $t->logo_url,
                ]);
        } else {
            // National teams are not linked via tournament_id — resolve them
            // through the rounds → matches graph of this tournament.
            $teamIds = \DB::table('matches')
                ->join('rounds', 'matches.round_id', '=', 'rounds.id')
                ->where('rounds.tournament_id', $tournament->id)
                ->whereNotNull('matches.home_team_id')
                ->whereNotNull('matches.away_team_id')
                ->selectRaw('home_team_id, away_team_id')
                ->get()
                ->flatMap(fn($row) => [$row->home_team_id, $row->away_team_id])
                ->unique()
                ->values();

            $teams = Team::with('country')
                ->whereIn('id', $teamIds)
                ->orderBy('name')
                ->get()
                ->map(fn($t) => [
                    'id'         => $t->id,
                    'name'       => $t->name,
                    'short_name' => $t->short_name,
                    // National teams store the flag on their country, not on team.logo_url
                    'logo_url'   => $t->logo_url ?? $t->country?->flag_url,
                ]);
        }

        return response()->json(['data' => $teams]);
    }

    public function addTeam(Request $request, string $slug): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();
        $this->authorizeCreator($request, $tournament);

        $request->validate([
            'name'       => 'required|string|max:100',
            'short_name' => 'required|string|max:10',
            'logo_url'   => 'nullable|string|max:500',
        ]);

        $team = Team::create([
            'name'             => $request->name,
            'short_name'       => strtoupper($request->short_name),
            'logo_url'         => $request->logo_url,
            'tournament_id'    => $tournament->id,
            'is_national_team' => false,
        ]);

        return response()->json([
            'data'    => ['id' => $team->id, 'name' => $team->name, 'short_name' => $team->short_name, 'logo_url' => $team->logo_url],
            'message' => 'Equipo agregado.',
        ], 201);
    }

    public function updateTeam(Request $request, string $slug, int $teamId): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();
        $this->authorizeCreator($request, $tournament);

        $data = $request->validate([
            'name'       => 'sometimes|string|max:100',
            'short_name' => 'sometimes|string|max:10',
            'logo_url'   => 'nullable|string|max:500',
        ]);

        $team = Team::where('id', $teamId)->where('tournament_id', $tournament->id)->firstOrFail();

        if (isset($data['name']))       $team->name       = $data['name'];
        if (isset($data['short_name'])) $team->short_name = strtoupper($data['short_name']);
        if (array_key_exists('logo_url', $data)) $team->logo_url = $data['logo_url'];

        $team->save();

        return response()->json([
            'data'    => ['id' => $team->id, 'name' => $team->name, 'short_name' => $team->short_name, 'logo_url' => $team->logo_url],
            'message' => 'Equipo actualizado.',
        ]);
    }

    public function removeTeam(Request $request, string $slug, int $teamId): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();
        $this->authorizeCreator($request, $tournament);

        $team = Team::where('id', $teamId)->where('tournament_id', $tournament->id)->firstOrFail();
        $team->delete();

        return response()->json(['message' => 'Equipo eliminado.']);
    }

    // ── Rounds ────────────────────────────────────────────────────────────

    public function rounds(string $slug): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();
        $rounds = $tournament->rounds()->withCount('matches')->get()
            ->map(fn($r) => [
                'id' => $r->id, 'name' => $r->name, 'type' => $r->type,
                'order' => $r->order, 'matches_count' => $r->matches_count,
            ]);

        return response()->json(['data' => $rounds]);
    }

    public function addRound(Request $request, string $slug): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();
        $this->authorizeCreator($request, $tournament);

        $request->validate(['name' => 'required|string|max:100']);

        $order = $tournament->rounds()->max('order') + 1;

        $round = Round::create([
            'tournament_id' => $tournament->id,
            'name'          => $request->name,
            'type'          => 'general',
            'order'         => $order,
        ]);

        return response()->json([
            'data'    => ['id' => $round->id, 'name' => $round->name, 'type' => $round->type, 'order' => $round->order, 'matches_count' => 0],
            'message' => 'Jornada agregada.',
        ], 201);
    }

    public function removeRound(Request $request, string $slug, int $roundId): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();
        $this->authorizeCreator($request, $tournament);

        $round = Round::where('id', $roundId)->where('tournament_id', $tournament->id)->firstOrFail();
        $round->delete();

        return response()->json(['message' => 'Jornada eliminada.']);
    }

    // ── Matches ───────────────────────────────────────────────────────────

    public function addMatch(Request $request, string $slug, int $roundId): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();
        $this->authorizeCreator($request, $tournament);

        $round = Round::where('id', $roundId)->where('tournament_id', $tournament->id)->firstOrFail();

        $request->validate([
            'home_team_id'   => 'required|integer|exists:teams,id',
            'away_team_id'   => 'required|integer|exists:teams,id|different:home_team_id',
            'scheduled_at'   => 'required|date',
            'venue'          => 'nullable|string|max:200',
        ]);

        $scheduledAt = $request->scheduled_at;

        $match = GameMatch::create([
            'round_id'              => $round->id,
            'home_team_id'          => $request->home_team_id,
            'away_team_id'          => $request->away_team_id,
            'scheduled_at'          => $scheduledAt,
            'prediction_closes_at'  => $scheduledAt,
            'venue'                 => $request->venue,
            'status'                => 'scheduled',
        ]);

        $match->load('homeTeam', 'awayTeam');

        return response()->json([
            'data' => [
                'id'           => $match->id,
                'scheduled_at' => $match->scheduled_at->toIso8601String(),
                'venue'        => $match->venue,
                'home_team'    => ['id' => $match->homeTeam->id, 'name' => $match->homeTeam->name, 'short_name' => $match->homeTeam->short_name],
                'away_team'    => ['id' => $match->awayTeam->id, 'name' => $match->awayTeam->name, 'short_name' => $match->awayTeam->short_name],
            ],
            'message' => 'Partido agregado.',
        ], 201);
    }

    public function updateMatch(Request $request, string $slug, int $roundId, int $matchId): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();
        $this->authorizeCreator($request, $tournament);

        $round = Round::where('id', $roundId)->where('tournament_id', $tournament->id)->firstOrFail();
        $match = GameMatch::where('id', $matchId)->where('round_id', $round->id)->firstOrFail();

        $data = $request->validate([
            'home_team_id' => 'sometimes|integer|exists:teams,id',
            'away_team_id' => 'sometimes|integer|exists:teams,id|different:home_team_id',
            'scheduled_at' => 'sometimes|date',
            'venue'        => 'nullable|string|max:200',
            'status'       => 'sometimes|in:scheduled,in_progress,finished,cancelled',
        ]);

        if (isset($data['scheduled_at'])) {
            $data['prediction_closes_at'] = $data['scheduled_at'];
        }

        $match->update($data);
        $match->load('homeTeam', 'awayTeam');

        return response()->json([
            'data' => [
                'id'           => $match->id,
                'scheduled_at' => $match->scheduled_at->toIso8601String(),
                'venue'        => $match->venue,
                'status'       => $match->status,
                'home_team'    => ['id' => $match->homeTeam->id, 'name' => $match->homeTeam->name, 'short_name' => $match->homeTeam->short_name],
                'away_team'    => ['id' => $match->awayTeam->id, 'name' => $match->awayTeam->name, 'short_name' => $match->awayTeam->short_name],
            ],
            'message' => 'Partido actualizado.',
        ]);
    }

    public function removeMatch(Request $request, string $slug, int $roundId, int $matchId): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();
        $this->authorizeCreator($request, $tournament);

        $round = Round::where('id', $roundId)->where('tournament_id', $tournament->id)->firstOrFail();
        $match = GameMatch::where('id', $matchId)->where('round_id', $round->id)->firstOrFail();
        $match->delete();

        return response()->json(['message' => 'Partido eliminado.']);
    }

    public function roundMatches(string $slug, int $roundId): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();
        $round = Round::where('id', $roundId)->where('tournament_id', $tournament->id)->firstOrFail();

        $matches = $round->matches()->with(['homeTeam', 'awayTeam', 'result'])->orderBy('scheduled_at')->get()
            ->map(fn($m) => [
                'id'               => $m->id,
                'scheduled_at'     => $m->scheduled_at->toIso8601String(),
                'venue'            => $m->venue,
                'status'           => $m->status,
                'home_team'        => $m->homeTeam ? ['id' => $m->homeTeam->id, 'name' => $m->homeTeam->name, 'short_name' => $m->homeTeam->short_name] : null,
                'home_placeholder' => $m->home_placeholder,
                'away_team'        => $m->awayTeam ? ['id' => $m->awayTeam->id, 'name' => $m->awayTeam->name, 'short_name' => $m->awayTeam->short_name] : null,
                'away_placeholder' => $m->away_placeholder,
                'result'           => $m->result ? ['home_score' => $m->result->home_score, 'away_score' => $m->result->away_score, 'winner' => $m->result->winner] : null,
            ]);

        return response()->json(['data' => $matches]);
    }

    public function setMatchResult(Request $request, string $slug, int $roundId, int $matchId): JsonResponse
    {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();
        $this->authorizeCreator($request, $tournament);

        $round = Round::where('id', $roundId)->where('tournament_id', $tournament->id)->firstOrFail();
        $match = GameMatch::where('id', $matchId)->where('round_id', $round->id)->firstOrFail();

        if ($match->scheduled_at->isFuture()) {
            return response()->json(['message' => 'No puedes ingresar el resultado de un partido que aún no ha iniciado.'], 422);
        }

        $request->validate([
            'home_score' => 'required|integer|min:0',
            'away_score' => 'required|integer|min:0',
        ]);

        $existing = MatchResult::where('match_id', $matchId)->first();

        if ($existing) {
            $existing->update([
                'home_score'   => $request->home_score,
                'away_score'   => $request->away_score,
                'confirmed_at' => now(),
            ]);
            RecalculateMatchScoresJob::dispatch($existing);
            $result = $existing;
        } else {
            $result = MatchResult::create([
                'match_id'     => $matchId,
                'home_score'   => $request->home_score,
                'away_score'   => $request->away_score,
                'confirmed_at' => now(),
            ]);
            $match->update(['status' => 'finished']);
            CalculateScoresJob::dispatch($result);
        }

        return response()->json([
            'data'    => [
                'home_score' => $result->home_score,
                'away_score' => $result->away_score,
                'winner'     => $result->winner,
            ],
            'message' => 'Resultado guardado. Los puntajes se están calculando.',
        ]);
    }

    private function authorizeCreator(Request $request, Tournament $tournament): void
    {
        if ($tournament->creator_id !== $request->user()->id) {
            abort(403, 'Solo el creador del torneo puede realizar esta acción.');
        }
    }
}
