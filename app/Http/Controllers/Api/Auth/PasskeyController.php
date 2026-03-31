<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\SecureUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;

/**
 * @group Authentication
 * @subgroup Passkeys (WebAuthn)
 * 
 * Passkey authentication using WebAuthn/FIDO2.
 */
class PasskeyController extends Controller
{
    /**
     * Get Registration Options.
     * 
     * Prepares the challenge for passkey registration.
     * 
     * @group Authentication
     * @subgroup Passkeys (WebAuthn)
     * @authenticated
     * 
     * @bodyParam name string The user-friendly name for this passkey. Example: My MacBook
     */
    public function registerOptions(AttestationRequest $request)
    {
        try {
            return $request->fastRegistration()->toCreate();
        } catch (\Exception $e) {
            Log::error('Failed to prepare passkey registration', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to prepare registration'], 500);
        }
    }

    /**
     * Register Passkey.
     * 
     * Completes the passkey registration by saving the public key.
     * 
     * @group Authentication
     * @subgroup Passkeys (WebAuthn)
     * @authenticated
     * 
     * @bodyParam id string required The credential ID.
     * @bodyParam rawId string required The raw credential ID.
     * @bodyParam type string required The credential type (public-key).
     * @bodyParam response object required The authenticator response.
     * @bodyParam clientExtensionResults object required The client extension results.
     */
    public function register(AttestedRequest $request): JsonResponse
    {
        try {
            $credential = $request->save();

            // Set the user-friendly name if provided
            if ($request->has('name')) {
                $credential->name = $request->name;
                $credential->save();
            }

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
     */
    public function index(Request $request)
    {
        $user = $request->user();

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
     */
    public function destroy(Request $request, string $id)
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
     * Get Authentication Options.
     * 
     * Prepares the challenge for passkey authentication.
     * 
     * @group Authentication
     * @subgroup Passkeys (WebAuthn)
     * @unauthenticated
     * 
     * @bodyParam email string required The user's email address. Example: user@example.com
     */
    public function authenticateOptions(AssertionRequest $request)
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

        $hasPasskeys = DB::table('webauthn_credentials')
            ->where('user_id', $user->id)
            ->exists();

        if (!$hasPasskeys) {
            return response()->json([
                'message' => 'No passkeys found for this user.',
            ], 404);
        }

        return $request->toVerify($user);
    }

    /**
     * Authenticate with Passkey.
     * 
     * Verifies the passkey challenge and logs the user in.
     * 
     * @group Authentication
     * @subgroup Passkeys (WebAuthn)
     * @unauthenticated
     * 
     * @bodyParam id string required The credential ID.
     * @bodyParam rawId string required The raw credential ID.
     * @bodyParam type string required The credential type (public-key).
     * @bodyParam response object required The authenticator response.
     * @bodyParam clientExtensionResults object required The client extension results.
     * @bodyParam remember boolean Whether to remember the session. Example: true
     */
    public function authenticate(AssertedRequest $request)
    {
        try {
            $remember = (bool) $request->input('remember', false);
            
            if ($request->login($remember)) {
                $user = $request->user();
                // Update last used timestamp
                DB::table('webauthn_credentials')
                    ->where('id', $request->input('id'))
                    ->update(['updated_at' => now()]);

                return response()->json([
                    'user' => new SecureUserResource($user),
                ]);
            }

            return response()->json([
                'message' => 'Passkey authentication failed.',
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Passkey authentication error: ' . $e->getMessage(),
            ], 422);
        }
    }
}
