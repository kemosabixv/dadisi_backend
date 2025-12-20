<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\SecureUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @group Authentication
 * @subgroup Two-Factor Authentication (TOTP)
 */
class TwoFactorController extends Controller
{
    /**
     * Enable TOTP Setup
     *
     * Generates a new TOTP secret and returns a QR code URL for the user to scan.
     * The secret is stored temporarily in session until verified.
     *
     * @authenticated
     * @response 200 {
     *   "secret": "JBSWY3DPEHPK3PXP",
     *   "qr_code_url": "otpauth://totp/Dadisi:user@example.com?secret=JBSWY3DPEHPK3PXP&issuer=Dadisi"
     * }
     */
    public function enable(Request $request)
    {
        $user = $request->user();

        // Check if already enabled
        if ($user->two_factor_enabled) {
            return response()->json([
                'message' => 'Two-factor authentication is already enabled.',
            ], 400);
        }

        $google2fa = app(\PragmaRX\Google2FALaravel\Google2FA::class);
        $secret = $google2fa->generateSecretKey();

        // Build OTP Auth URL for QR code
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'Dadisi'),
            $user->email,
            $secret
        );

        // Store secret temporarily in session for verification
        session(['2fa_secret' => $secret]);

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ]);
    }

    /**
     * Verify TOTP Code
     *
     * Verifies the TOTP code from the authenticator app and enables 2FA.
     * Returns recovery codes that the user should save securely.
     *
     * @authenticated
     * @bodyParam code string required The 6-digit code from the authenticator app. Example: 123456
     * @response 200 {
     *   "message": "Two-factor authentication has been enabled.",
     *   "recovery_codes": ["abc123", "def456", "ghi789", ...]
     * }
     * @response 422 {
     *   "message": "The provided code is invalid."
     * }
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        $secret = session('2fa_secret');

        if (!$secret) {
            return response()->json([
                'message' => 'Please start the 2FA setup process first.',
            ], 400);
        }

        $google2fa = app(\PragmaRX\Google2FALaravel\Google2FA::class);

        if (!$google2fa->verifyKey($secret, $request->code)) {
            return response()->json([
                'message' => 'The provided code is invalid.',
            ], 422);
        }

        // Generate recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();

        // Enable 2FA
        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_enabled' => true,
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        // Clear session
        session()->forget('2fa_secret');

        return response()->json([
            'message' => 'Two-factor authentication has been enabled.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable TOTP
     *
     * Disables two-factor authentication. Requires password confirmation.
     *
     * @authenticated
     * @bodyParam password string required The user's current password. Example: Pass123!@#
     * @response 200 {
     *   "message": "Two-factor authentication has been disabled."
     * }
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!password_verify($request->password, $user->password)) {
            return response()->json([
                'message' => 'The provided password is incorrect.',
            ], 422);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'message' => 'Two-factor authentication has been disabled.',
        ]);
    }

    /**
     * Validate TOTP Code (at login)
     *
     * Validates the TOTP code during login when 2FA is enabled.
     * Call this after successful password authentication.
     *
     * @bodyParam email string required The user's email. Example: user@example.com
     * @bodyParam code string required The 6-digit code from the authenticator app. Example: 123456
     * @response 200 {
     *   "user": {...},
     *   "access_token": "1|abc123..."
     * }
     * @response 422 {
     *   "message": "The provided code is invalid."
     * }
     */
    public function validateCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user || !$user->two_factor_enabled) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled for this user.',
            ], 400);
        }

        $code = $request->code;
        $isValid = false;

        // Check if it's a recovery code
        if (strlen($code) > 6) {
            $isValid = $this->validateRecoveryCode($user, $code);
        } else {
            // Validate TOTP code
            $google2fa = app(\PragmaRX\Google2FALaravel\Google2FA::class);
            $secret = decrypt($user->two_factor_secret);
            $isValid = $google2fa->verifyKey($secret, $code);
        }

        if (!$isValid) {
            return response()->json([
                'message' => 'The provided code is invalid.',
            ], 422);
        }

        // Issue token
        $token = $user->createToken('2fa-login')->plainTextToken;

        return response()->json([
            'user' => new SecureUserResource($user),
            'access_token' => $token,
        ]);
    }

    /**
     * Get Recovery Codes
     *
     * Returns the current recovery codes. Requires password confirmation.
     *
     * @authenticated
     * @bodyParam password string required The user's current password. Example: Pass123!@#
     */
    public function recoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!password_verify($request->password, $user->password)) {
            return response()->json([
                'message' => 'The provided password is incorrect.',
            ], 422);
        }

        if (!$user->two_factor_enabled) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled.',
            ], 400);
        }

        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        return response()->json([
            'recovery_codes' => $codes,
        ]);
    }

    /**
     * Regenerate Recovery Codes
     *
     * Generates new recovery codes and invalidates the old ones.
     *
     * @authenticated
     * @bodyParam password string required The user's current password. Example: Pass123!@#
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!password_verify($request->password, $user->password)) {
            return response()->json([
                'message' => 'The provided password is incorrect.',
            ], 422);
        }

        if (!$user->two_factor_enabled) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled.',
            ], 400);
        }

        $newCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($newCodes)),
        ])->save();

        return response()->json([
            'recovery_codes' => $newCodes,
        ]);
    }

    /**
     * Generate a set of recovery codes.
     */
    protected function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::random(10) . '-' . Str::random(10);
        }
        return $codes;
    }

    /**
     * Validate and consume a recovery code.
     */
    protected function validateRecoveryCode($user, string $code): bool
    {
        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        if (in_array($code, $codes)) {
            // Remove used code
            $codes = array_values(array_diff($codes, [$code]));
            $user->forceFill([
                'two_factor_recovery_codes' => encrypt(json_encode($codes)),
            ])->save();
            return true;
        }

        return false;
    }
}
