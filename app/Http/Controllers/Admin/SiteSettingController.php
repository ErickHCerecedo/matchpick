<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = SiteSetting::all()->pluck('value', 'key');
        return response()->json(['data' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bg_url' => 'nullable|string|max:500',
        ]);

        SiteSetting::updateOrCreate(
            ['key' => 'bg_url'],
            ['value' => $validated['bg_url'] ?? null]
        );

        return response()->json(['message' => 'Configuración guardada']);
    }
}
