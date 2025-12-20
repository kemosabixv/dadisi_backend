<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPublicKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KeyManagementController extends Controller
{
    /**
     * Store or update the authenticated user's public key.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'public_key' => 'required|string',
        ]);

        $publicKey = UserPublicKey::updateOrCreate(
            ['user_id' => Auth::id()],
            ['public_key' => $validated['public_key']]
        );

        return response()->json([
            'data' => $publicKey,
            'message' => 'Public key stored successfully.',
        ]);
    }

    /**
     * Get a user's public key by user ID.
     */
    public function show(int $userId): JsonResponse
    {
        $publicKey = UserPublicKey::where('user_id', $userId)->first();

        if (!$publicKey) {
            return response()->json([
                'message' => 'User has not set up encrypted messaging.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'user_id' => $publicKey->user_id,
                'public_key' => $publicKey->public_key,
            ],
        ]);
    }

    /**
     * Get the authenticated user's public key.
     */
    public function me(): JsonResponse
    {
        $publicKey = Auth::user()->publicKey;

        return response()->json([
            'data' => $publicKey,
            'has_key' => (bool) $publicKey,
        ]);
    }

    /**
     * Delete the authenticated user's public key.
     */
    public function destroy(): JsonResponse
    {
        Auth::user()->publicKey?->delete();

        return response()->json([
            'message' => 'Public key deleted. Encrypted messaging is now disabled.',
        ]);
    }
}
