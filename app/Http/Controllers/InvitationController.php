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
        $invitation = Invitation::where('token', $token)
            ->with(['quiniela.tournament', 'quiniela.creator', 'quiniela.participants'])
            ->firstOrFail();

        if ($invitation->status === 'expired' || $invitation->expires_at->isPast()) {
            return response()->json(['message' => 'Esta invitación ha expirado.'], 410);
        }

        $quiniela = $invitation->quiniela;

        return response()->json([
            'data' => [
                'token'      => $invitation->token,
                'status'     => $invitation->status,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'quiniela'   => [
                    'id'                 => $quiniela->id,
                    'name'               => $quiniela->name,
                    'slug'               => $quiniela->slug,
                    'type'               => $quiniela->type,
                    'participants_count' => $quiniela->participants->count(),
                    'tournament'         => [
                        'id'   => $quiniela->tournament->id,
                        'name' => $quiniela->tournament->name,
                        'slug' => $quiniela->tournament->slug,
                    ],
                    'creator' => $quiniela->creator ? [
                        'id'         => $quiniela->creator->id,
                        'name'       => $quiniela->creator->name,
                        'avatar_url' => $quiniela->creator->avatar_url,
                    ] : null,
                ],
            ],
        ]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = Invitation::where('token', $token)
            ->where('expires_at', '>', now())
            ->where('status', 'pending')
            ->with('quiniela')
            ->firstOrFail();

        $user = $request->user();
        $quiniela = $invitation->quiniela;

        // Already a participant — return success so the frontend redirects
        if ($quiniela->participants()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'Ya eres participante de esta quiniela.',
                'data'    => ['slug' => $quiniela->slug],
            ]);
        }

        $quiniela->participants()->attach($user->id, ['role' => 'participant']);
        Standing::firstOrCreate(
            ['quiniela_id' => $quiniela->id, 'user_id' => $user->id]
        );

        $invitation->update(['status' => 'accepted']);

        return response()->json([
            'message' => '¡Te uniste a la quiniela exitosamente!',
            'data'    => ['slug' => $quiniela->slug],
        ]);
    }
}
