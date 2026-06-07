<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request, CloudinaryService $cloudinary): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'username' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('users')->ignore($user->id),
            ],
            'avatar'   => 'sometimes|nullable|image|max:5120',
        ]);

        if ($request->hasFile('avatar')) {
            $file     = $request->file('avatar');
            $namePart = preg_replace('/\s+/', '_', strtolower($user->name));
            $publicId = "{$namePart}_{$user->id}";

            if ($user->avatar_url && str_contains($user->avatar_url, 'cloudinary.com')) {
                $oldPublicId = $cloudinary->extractPublicId($user->avatar_url);
                if ($oldPublicId) {
                    try {
                        $cloudinary->delete($oldPublicId);
                    } catch (\Throwable $e) {
                        logger()->warning('Cloudinary delete failed', [
                            'public_id' => $oldPublicId,
                            'error'     => $e->getMessage(),
                        ]);
                    }
                }
            }

            $validated['avatar_url'] = $cloudinary->upload($file, $publicId);
        }

        unset($validated['avatar']);

        if (!empty($validated)) {
            $user->update($validated);
        }

        return response()->json(['data' => new UserResource($user->fresh())]);
    }
}
