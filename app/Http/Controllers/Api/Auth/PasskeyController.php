<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\SecureUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @group Authentication
 * @subgroup Passkeys (WebAuthn)
 * 
 * Passkey authentication using WebAuthn/FIDO2.
 * Requires the laragear/webauthn package to be installed.
 */
class PasskeyController extends Controller
{
    /**
     * Get Registration Options
     *
     * Returns the options needed to register a new passkey.
     * The frontend uses these options with the WebAuthn API.
     *
     * @authenticated
     * @response 200 {
     *   "challenge": "base64-encoded-challenge",
     *   "rp": { "name": "Dadisi", "id": "dadisilab.com" },
     *   "user": { "id": "base64-user-id", "name": "user@example.com", "displayName": "User" },
     *   "pubKeyCredParams": [...],
     *   "timeout": 60000,
     *   "attestation": "none",
     *   "authenticatorSelection": { "residentKey": "preferred", "userVerification": "preferred" }
     * }
     */
    public function registerOptions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if WebAuthn is available
            if (!class_exists(\Laragear\WebAuthn\WebAuthn::class)) {
                return response()->json([
                    'message' => 'WebAuthn is not configured. Please install laragear/webauthn package.',
                ], 501);
            }

            return \Laragear\WebAuthn\WebAuthn::prepareAttestation($user);
        } catch (\Exception $e) {
            Log::error('Failed to prepare passkey registration', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to prepare registration'], 500);
        }
    }

    /**
     * Register Passkey
     *
     * Registers a new passkey after the user completes the WebAuthn ceremony.
     *
     * @authenticated
     * @bodyParam name string required A friendly name for this passkey. Example: iPhone 15 Pro
     * @bodyParam id string required The credential ID from WebAuthn. Example: base64-credential-id
     * @bodyParam rawId string required The raw credential ID. Example: base64-raw-id
     * @bodyParam response object required The authenticator response containing attestationObject and clientDataJSON.
     * @bodyParam type string required Must be "public-key". Example: public-key
     * @response 201 {
     *   "message": "Passkey registered successfully.",
     *   "passkey": {
     *     "id": 1,
     *     "name": "iPhone 15 Pro",
     *     "created_at": "2025-12-20T10:00:00Z"
     *   }
     * }
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $user = $request->user();

            // Check if WebAuthn is available
            if (!class_exists(\Laragear\WebAuthn\WebAuthn::class)) {
                return response()->json([
                    'message' => 'WebAuthn is not configured. Please install laragear/webauthn package.',
                ], 501);
            }

            $credential = \Laragear\WebAuthn\WebAuthn::validateAttestation(
                $request->all(),
                $user
            );

            // Set the user-friendly name
            $credential->name = $request->name;
            $credential->save();

            return response()->json([
                'message' => 'Passkey registered successfully.',
                'passkey' => [
                    'id' => $credential->id,
                    'name' => $credential->name,
                    'created_at' => $credential->created_at->toIso8601String(),
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to register passkey', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to register passkey: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * List Passkeys
     *
     * Returns all passkeys registered for the authenticated user.
     *
     * @authenticated
     * @response 200 {
     *   "passkeys": [
     *     { "id": 1, "name": "iPhone 15 Pro", "created_at": "2025-12-20T10:00:00Z", "last_used_at": "2025-12-22T09:00:00Z" },
     *     { "id": 2, "name": "Yubikey 5C", "created_at": "2025-12-21T14:00:00Z", "last_used_at": null }
     *   ]
     * }
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Query webauthn_credentials table directly if package not installed
        $passkeys = DB::table('webauthn_credentials')
            ->where('user_id', $user->id)
            ->select('id', 'name', 'created_at', 'updated_at as last_used_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'passkeys' => $passkeys,
        ]);
    }

    /**
     * Delete Passkey
     *
     * Removes a passkey from the user's account.
     *
     * @authenticated
     * @urlParam id integer required The passkey ID. Example: 1
     * @response 200 {
     *   "message": "Passkey removed successfully."
     * }
     * @response 404 {
     *   "message": "Passkey not found."
     * }
     */
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();

        $deleted = DB::table('webauthn_credentials')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Passkey not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'Passkey removed successfully.',
        ]);
    }

    /**
     * Get Authentication Options
     *
     * Returns the options needed to authenticate with a passkey.
     * Used on the login page when the user wants to sign in with a passkey.
     *
     * @bodyParam email string required The user's email address. Example: user@example.com
     * @response 200 {
     *   "challenge": "base64-encoded-challenge",
     *   "timeout": 60000,
     *   "rpId": "dadisilab.com",
     *   "allowCredentials": [...]
     * }
     * @response 404 {
     *   "message": "No passkeys found for this user."
     * }
     */
    public function authenticateOptions(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'No passkeys found for this user.',
            ], 404);
        }

        // Check if user has any passkeys
        $hasPasskeys = DB::table('webauthn_credentials')
            ->where('user_id', $user->id)
            ->exists();

        if (!$hasPasskeys) {
            return response()->json([
                'message' => 'No passkeys found for this user.',
            ], 404);
        }

        // Check if WebAuthn is available
        if (!class_exists(\Laragear\WebAuthn\WebAuthn::class)) {
            return response()->json([
                'message' => 'WebAuthn is not configured. Please install laragear/webauthn package.',
            ], 501);
        }

        return \Laragear\WebAuthn\WebAuthn::prepareAssertion($user);
    }

    /**
     * Authenticate with Passkey
     *
     * Validates the passkey assertion and logs the user in.
     *
     * @bodyParam id string required The credential ID. Example: base64-credential-id
     * @bodyParam rawId string required The raw credential ID. Example: base64-raw-id
     * @bodyParam response object required The authenticator response.
     * @bodyParam type string required Must be "public-key". Example: public-key
     * @response 200 {
     *   "user": {
     *     "id": 2,
     *     "username": "jane_doe",
     *     "email": "jane.doe@example.com"
     *   },
     *   "access_token": "4|passkey-token-secret-123..."
     * }
     * @response 422 {
     *   "message": "Passkey authentication failed."
     * }
     */
    public function authenticate(Request $request)
    {
        // Check if WebAuthn is available
        if (!class_exists(\Laragear\WebAuthn\WebAuthn::class)) {
            return response()->json([
                'message' => 'WebAuthn is not configured. Please install laragear/webauthn package.',
            ], 501);
        }

        try {
            $credential = \Laragear\WebAuthn\WebAuthn::validateAssertion($request->all());
            $user = $credential->user;

            // Update last used timestamp
            $credential->touch();

            // Issue Sanctum token
            $token = $user->createToken('passkey-auth')->plainTextToken;

            return response()->json([
                'user' => new SecureUserResource($user),
                'access_token' => $token,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Passkey authentication failed: ' . $e->getMessage(),
            ], 422);
        }
    }
}
