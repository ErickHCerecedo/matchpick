<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateInvitationRequest;
use App\Models\Invitation;
use App\Models\Quiniela;
use App\Models\Standing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function store(CreateInvitationRequest $request, string $slug): JsonResponse
    {
        $quiniela = Quiniela::where('slug', $slug)->firstOrFail();

        // Only admin can invite
        $pivot = $quiniela->participants()->where('user_id', $request->user()->id)->first();
        if (!$pivot || $pivot->pivot->role !== 'admin') {
            return response()->json(['message' => 'Only admins can create invitations.'], 403);
        }

        $invitation = Invitation::create([
            'quiniela_id' => $quiniela->id,
            'invited_by' => $request->user()->id,
            'email' => $request->email,
            'token' => Str::uuid(),
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'data' => ['token' => $invitation->token, 'expires_at' => $invitation->expires_at],
            'message' => 'Invitation created.',
        ], 201);
    }

    public function show(string $token): JsonResponse
    {
        $invitation = Invitation::where('token', $token)->with('quiniela.tournament')->firstOrFail();

        if ($invitation->status === 'expired' || $invitation->expires_at->isPast()) {
            return response()->json(['message' => 'Invitation has expired.'], 410);
        }

        return response()->json(['data' => $invitation]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = Invitation::valid()->where('token', $token)->with('quiniela')->firstOrFail();

        if (!$invitation) {
            return response()->json(['message' => 'Invalid or expired invitation.'], 410);
        }

        $user = $request->user();
        $quiniela = $invitation->quiniela;

        // Already a participant
        if ($quiniela->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are already a participant.'], 409);
        }

        $quiniela->participants()->attach($user->id, ['role' => 'participant']);
        Standing::create(['quiniela_id' => $quiniela->id, 'user_id' => $user->id]);

        $invitation->update(['status' => 'accepted']);

        return response()->json(['message' => 'Joined quiniela successfully.', 'data' => ['slug' => $quiniela->slug]]);
    }
}
