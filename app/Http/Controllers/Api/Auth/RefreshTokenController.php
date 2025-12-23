<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\SecureUserResource;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @group Authentication
 */
class RefreshTokenController extends Controller
{
    /**
     * Refresh Token
     *
     * Rotates the current authentication token by revoking it and issuing a new one.
     * This endpoint extends the session and returns a fresh token.
     * Use this for silent token refresh before expiry.
     *
     * @authenticated
     * @response 200 {
     *   "user": {
     *     "id": 2,
     *     "username": "jane_doe",
     *     "email": "jane.doe@example.com"
     *   },
     *   "access_token": "5|newTokenExample123...",
     *   "expires_at": "2026-01-15T12:00:00Z"
     * }
     * @response 401 {
     *   "message": "Unauthenticated."
     * }
     */
    public function refresh(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Verify the current token is still valid
        $bearer = $request->bearerToken();
        if ($bearer) {
            $tokenModel = PersonalAccessToken::findToken($bearer);
            if (!$tokenModel) {
                return response()->json(['message' => 'Token has been revoked.'], 401);
            }
        }

        // Revoke current token
        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $currentToken->delete();
        }

        // Calculate expiration based on config
        $expirationMinutes = config('sanctum.expiration');
        $expiresAt = $expirationMinutes ? now()->addMinutes($expirationMinutes) : null;

        // Create new token
        $tokenResult = $user->createToken('refresh-token');
        $newToken = $tokenResult->plainTextToken;

        // Set expiration on the new token if configured
        if ($expiresAt && $tokenResult->accessToken) {
            $tokenResult->accessToken->expires_at = $expiresAt;
            $tokenResult->accessToken->save();
        }

        return response()->json([
            'user' => new SecureUserResource($user),
            'access_token' => $newToken,
            'expires_at' => $expiresAt ? $expiresAt->toIso8601String() : null,
        ]);
    }
}
