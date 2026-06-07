<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateQuinielaRequest;
use App\Http\Requests\UpdateQuinielaRequest;
use App\Http\Resources\QuinielaResource;
use App\Http\Resources\MatchResource;
use App\Models\GameMatch;
use App\Models\Quiniela;
use App\Models\Standing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class QuinielaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user      = $request->user();
        $quinielas = $user->participatingQuinielas()
            ->with(['tournament', 'creator'])
            ->withCount('participants')
            ->active()
            ->get();

        if ($quinielas->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $quinielaIds = $quinielas->pluck('id');
        $userId      = $user->id;

        // Batch-load all standings so rank can be computed without N+1 queries
        $standingsByQuiniela = Standing::whereIn('quiniela_id', $quinielaIds)
            ->orderBy('quiniela_id')
            ->orderByDesc('total_points')
            ->orderByDesc('exact_scores')
            ->get()
            ->groupBy('quiniela_id');

        $myStandings = [];
        foreach ($standingsByQuiniela as $qId => $standings) {
            foreach ($standings as $rank => $s) {
                if ($s->user_id === $userId) {
                    $myStandings[(int) $qId] = [
                        'rank'         => $rank + 1,
                        'total_points' => $s->total_points,
                    ];
                    break;
                }
            }
        }

        // Count open matches the user has not yet predicted in each quiniela
        $pendingCounts = [];
        foreach ($quinielas as $q) {
            $pendingCounts[$q->id] = GameMatch::whereHas(
                'round',
                fn ($r) => $r->where('tournament_id', $q->tournament_id)
            )
                ->where('prediction_closes_at', '>', now())
                ->whereDoesntHave(
                    'predictions',
                    fn ($p) => $p->where('user_id', $userId)->where('quiniela_id', $q->id)
                )
                ->count();
        }

        $data = $quinielas->map(function ($q) use ($myStandings, $pendingCounts) {
            $resource                              = (new QuinielaResource($q))->resolve();
            $resource['my_standing']               = $myStandings[$q->id] ?? null;
            $resource['pending_predictions_count'] = $pendingCounts[$q->id] ?? 0;
            return $resource;
        });

        return response()->json(['data' => $data]);
    }

    public function store(CreateQuinielaRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->quinielas_created_count >= 5) {
            return response()->json(['message' => 'You can create a maximum of 5 quinielas.'], 422);
        }

        $quiniela = Quiniela::create([
            'creator_id'       => $user->id,
            'tournament_id'    => $request->tournament_id,
            'name'             => $request->name,
            'slug'             => Quiniela::generateSlug($request->name),
            'description'      => $request->description,
            'type'             => $request->type ?? 'private',
            'max_participants' => $request->max_participants,
        ]);

        $quiniela->participants()->attach($user->id, ['role' => 'admin']);
        Standing::create(['quiniela_id' => $quiniela->id, 'user_id' => $user->id]);
        $user->increment('quinielas_created_count');

        return response()->json([
            'data'    => new QuinielaResource($quiniela->load(['tournament', 'creator'])),
            'message' => 'Quiniela created successfully.',
        ], 201);
    }

    public function show(string $slug): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)
            ->with(['tournament', 'creator', 'participants'])
            ->firstOrFail();

        return response()->json(['data' => new QuinielaResource($quiniela)]);
    }

    public function update(UpdateQuinielaRequest $request, string $slug): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();
        $this->authorizeAdmin($request, $quiniela);

        $quiniela->update($request->only('name', 'description', 'type', 'max_participants', 'predictions_open'));

        return response()->json(['data' => new QuinielaResource($quiniela)]);
    }

    // ── Deletion with safety rules ──────────────────────────────────────────

    /**
     * Execute deletion. Rules:
     *  - Pre-tournament (> 5 days before start): admin can delete freely.
     *  - Post-tournament (> 5 days after end): admin can delete freely.
     *  - Active: unanimous vote from all participants required.
     */
    public function destroy(Request $request, string $slug): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)
            ->with(['tournament', 'participants'])
            ->firstOrFail();

        $this->authorizeAdmin($request, $quiniela);

        $now      = Carbon::now();
        $startsAt = Carbon::parse($quiniela->tournament->starts_at);
        $endsAt   = Carbon::parse($quiniela->tournament->ends_at);

        $preWindow  = $now->copy()->addDays(5)->lt($startsAt);
        $postWindow = $now->copy()->subDays(5)->gt($endsAt);

        if (!$preWindow && !$postWindow) {
            $participantsCount = $quiniela->participants()->count();
            $votesCount        = $quiniela->deleteVotes()->count();

            if ($votesCount < $participantsCount) {
                return response()->json([
                    'message'            => 'Se requiere el acuerdo de todos los participantes para eliminar.',
                    'votes_count'        => $votesCount,
                    'participants_count' => $participantsCount,
                ], 422);
            }
        }

        $quiniela->delete();
        $request->user()->decrement('quinielas_created_count');

        return response()->json(['message' => 'Quiniela eliminada correctamente.']);
    }

    /** Returns the current deletion-vote status for the authenticated participant. */
    public function deleteStatus(Request $request, string $slug): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)
            ->with(['tournament', 'participants'])
            ->firstOrFail();

        if (!$quiniela->participants()->where('user_id', $request->user()->id)->exists()) {
            abort(403, 'No eres participante de esta quiniela.');
        }

        $now      = Carbon::now();
        $startsAt = Carbon::parse($quiniela->tournament->starts_at);
        $endsAt   = Carbon::parse($quiniela->tournament->ends_at);

        $preWindow  = $now->copy()->addDays(5)->lt($startsAt);
        $postWindow = $now->copy()->subDays(5)->gt($endsAt);

        $reason = match (true) {
            $preWindow  => 'pre_tournament',
            $postWindow => 'post_tournament',
            default     => 'active',
        };

        $participantsCount = $quiniela->participants()->count();
        $votesCount        = $quiniela->deleteVotes()->count();
        $myVote            = $quiniela->deleteVotes()->where('user_id', $request->user()->id)->exists();

        // Check whether the admin has cast a vote (marks that the admin initiated deletion)
        $adminUser  = $quiniela->participants()->wherePivot('role', 'admin')->first();
        $adminVoted = $adminUser
            ? $quiniela->deleteVotes()->where('user_id', $adminUser->id)->exists()
            : false;

        return response()->json([
            'data' => [
                'can_delete_freely'  => $preWindow || $postWindow,
                'reason'             => $reason,
                'votes_count'        => $votesCount,
                'participants_count' => $participantsCount,
                'my_vote'            => $myVote,
                'admin_voted'        => $adminVoted,
                'can_delete'         => ($preWindow || $postWindow) || ($votesCount >= $participantsCount),
            ],
        ]);
    }

    /** Cast (or renew) the authenticated user's vote to delete this quiniela. */
    public function castDeleteVote(Request $request, string $slug): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();

        if (!$quiniela->participants()->where('user_id', $request->user()->id)->exists()) {
            abort(403, 'No eres participante de esta quiniela.');
        }

        $quiniela->deleteVotes()->firstOrCreate(['user_id' => $request->user()->id]);

        return response()->json([
            'message'            => 'Voto registrado.',
            'votes_count'        => $quiniela->deleteVotes()->count(),
            'participants_count' => $quiniela->participants()->count(),
        ]);
    }

    /** Revoke the authenticated user's vote to delete. */
    public function revokeDeleteVote(Request $request, string $slug): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();

        $quiniela->deleteVotes()->where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Voto revocado.']);
    }

    // ── Matches ─────────────────────────────────────────────────────────────

    public function matches(Request $request, string $slug): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();
        $user     = $request->user();

        $rounds = $quiniela->tournament->rounds()->with([
            'matches' => function ($q) use ($user, $quiniela) {
                $q->with([
                    'homeTeam.country',
                    'awayTeam.country',
                    'result',
                    'predictions' => fn($p) => $p->where('user_id', $user->id)
                        ->where('quiniela_id', $quiniela->id),
                ])->orderBy('scheduled_at');
            },
        ])->get();

        $data = $rounds->map(fn($round) => [
            'round'   => $round->only('id', 'name', 'type', 'order'),
            'matches' => $round->matches->map(fn($match) => array_merge(
                (new MatchResource($match))->resolve(),
                ['my_prediction' => $match->predictions->first()]
            )),
        ]);

        return response()->json(['data' => $data]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function authorizeAdmin(Request $request, Quiniela $quiniela): void
    {
        $pivot = $quiniela->participants()->where('user_id', $request->user()->id)->first();
        if (!$pivot || $pivot->pivot->role !== 'admin') {
            abort(403, 'Only quiniela admins can perform this action.');
        }
    }
}
