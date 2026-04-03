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
use Illuminate\Support\Str;
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
            $credentialName = $request->input('name', 'Passkey');
            $id = $request->save(['name' => $credentialName]);

            // Get the credential model instance
            $credential = \Laragear\WebAuthn\Models\WebAuthnCredential::find($id);

            if (!$credential) {
                throw new \Exception('Failed to retrieve registered passkey.');
            }

            $message = 'Passkey registered successfully.';
            $recoveryCodes = [];

            // Recovery logic only if MFA is enabled or being enabled
            $user = $request->user();
            $passkeyCount = DB::table('webauthn_credentials')
                ->where('authenticatable_id', $user->id)
                ->where('authenticatable_type', $user->getMorphClass())
                ->count();

            if ($user->two_factor_enabled && $passkeyCount === 1 && empty($user->two_factor_recovery_codes)) {
                $recoveryCodes = $this->generateRecoveryCodes();
                $user->forceFill([
                    'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
                ])->save();
                $message .= ' Recovery codes have been generated for your account.';
            }

            return response()->json([
                'message' => $message,
                'passkey' => [
                    'id' => $credential->id,
                    'name' => $credential->name,
                    'created_at' => $credential->created_at->toIso8601String(),
                ],
                'recovery_codes' => $recoveryCodes,
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
        $morphClass = $user->getMorphClass();

        $passkeys = DB::table('webauthn_credentials')
            ->where('authenticatable_id', $user->id)
            ->where('authenticatable_type', $morphClass)
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
        $morphClass = $user->getMorphClass();

        $deleted = DB::table('webauthn_credentials')
            ->where('id', $id)
            ->where('authenticatable_id', $user->id)
            ->where('authenticatable_type', $morphClass)
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
        // For discoverable credentials (resident keys), the email is optional in the challenge phase.
        // If provided, we filter by user. If not, the authenticator will provide the user list.
        $email = $request->input('email');
        $user = $email ? User::where('email', $email)->first() : null;

        // If email was provided but user not found, 404
        if ($email && !$user) {
            return response()->json([
                'message' => 'No account found with this email.',
            ], 404);
        }

        // Return the challenge options. 
        // Laragear/WebAuthn handle the 'null' user case for discoverable credentials.
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
            
            if ($request->login('web', $remember)) {
                $user = $request->user();
                // Update last used timestamp
                DB::table('webauthn_credentials')
                    ->where('id', $request->input('id'))
                    ->update(['updated_at' => now()]);

                // Mark session as MFA satisfied since Passkey is 2FA inherent
                session()->put('auth.mfa_satisfied', true);

                return response()->json([
                    'user' => new SecureUserResource($user),
                    'requires_mfa' => false, // Override any password-based MFA requirement
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

    /**
     * Generate a set of recovery codes.
     */
    protected function generateRecoveryCodes(int $count = 8): array
    {
        return array_map(function () {
            return Str::random(10) . '-' . Str::random(10);
        }, range(1, $count));
    }
}
