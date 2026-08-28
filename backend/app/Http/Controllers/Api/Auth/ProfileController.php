<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * The signed-in user's profile and notification preferences.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $user->load(['school', 'student', 'teacher']),
            'sessions' => $user->tokens()
                ->latest('last_used_at')
                ->get(['id', 'name', 'last_used_at', 'created_at']),
        ]);
    }

    /**
     * Update name, contact details, language and notification preferences.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->fill($request->validated())->save();

        return response()->json([
            'message' => 'Profile updated.',
            'data' => $user->fresh()->load(['school', 'student', 'teacher']),
        ]);
    }

    /**
     * Revoke every token except the one making this request.
     */
    public function signOutOthers(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken()?->id;

        $request->user()->tokens()
            ->when($current, fn ($query) => $query->where('id', '!=', $current))
            ->delete();

        return response()->json(['message' => 'All other sessions have been signed out.']);
    }
}
