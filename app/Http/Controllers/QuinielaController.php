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

class QuinielaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $quinielas = $request->user()->participatingQuinielas()
            ->with(['tournament', 'creator'])
            ->active()
            ->get();

        return response()->json(['data' => QuinielaResource::collection($quinielas)]);
    }

    public function store(CreateQuinielaRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->quinielas_created_count >= 5) {
            return response()->json(['message' => 'You can create a maximum of 5 quinielas.'], 422);
        }

        $quiniela = Quiniela::create([
            'creator_id' => $user->id,
            'tournament_id' => $request->tournament_id,
            'name' => $request->name,
            'slug' => Quiniela::generateSlug($request->name),
            'description' => $request->description,
            'type' => $request->type ?? 'private',
            'max_participants' => $request->max_participants,
        ]);

        // Creator joins as admin
        $quiniela->participants()->attach($user->id, ['role' => 'admin']);

        // Create standing row for creator
        Standing::create(['quiniela_id' => $quiniela->id, 'user_id' => $user->id]);

        // Increment counter
        $user->increment('quinielas_created_count');

        return response()->json([
            'data' => new QuinielaResource($quiniela->load(['tournament', 'creator'])),
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

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();
        $this->authorizeAdmin($request, $quiniela);

        $quiniela->delete();
        $request->user()->decrement('quinielas_created_count');

        return response()->json(['message' => 'Quiniela deleted.']);
    }

    public function matches(Request $request, string $slug): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();
        $user = $request->user();

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
            'round' => $round->only('id', 'name', 'type', 'order'),
            'matches' => $round->matches->map(fn($match) => array_merge(
                (new MatchResource($match))->resolve(),
                ['my_prediction' => $match->predictions->first()]
            )),
        ]);

        return response()->json(['data' => $data]);
    }

    private function authorizeAdmin(Request $request, Quiniela $quiniela): void
    {
        $pivot = $quiniela->participants()->where('user_id', $request->user()->id)->first();
        if (!$pivot || $pivot->pivot->role !== 'admin') {
            abort(403, 'Only quiniela admins can perform this action.');
        }
    }
}
