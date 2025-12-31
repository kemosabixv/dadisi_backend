<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmail;
use App\Mail\WelomeAndVerifyEmail;
use App\Models\EmailVerification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * @group Authentication
 * @groupDescription Specific endpoints for handling the email verification flow (Post-Registration).
 */
class EmailVerificationController extends Controller
{
    /**
     * Send Verification Email
     *
     * Triggers the dispatch of a 6-character email verification code to the authenticated user's registered email.
     * This endpoint is rate-limited to one request per minute to prevent abuse.
     * Use this if the initial registration email was missed or expired.
     *
     * @authenticated
     * @response 200 {
     *   "message": "Verification email sent"
     * }
     * @response 400 {
     *   "message": "Email already verified"
     * }
     * @response 429 {
     *   "message": "Verification recently sent, try again later"
     * }
     */
    public function send(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->email_verified_at) {
                return response()->json(['message' => 'Email already verified'], 400);
            }

            // Rate limit: one attempt per minute
            $recentAttempt = EmailVerification::where('user_id', $user->id)
                ->where('created_at', '>', now()->subMinute())
                ->exists();

            if ($recentAttempt) {
                return response()->json([
                    'message' => 'Verification recently sent, try again later'
                ], 429);
            }

            // Generate 6-char code and set 24h expiry
            $code = strtoupper(Str::random(6));
            $expiresAt = now()->addDay();

            EmailVerification::create([
                'user_id' => $user->id,
                'code' => $code,
                'expires_at' => $expiresAt,
            ]);

            // Build frontend verify URL with code only for security
            $verifyUrl = config('app.frontend_url', env('FRONTEND_URL')) .
                '/verify-email?code=' . $code;
            $baseUrl = config('app.frontend_url', env('FRONTEND_URL')) . '/verify-email';

            Mail::to($user->email)
                ->send(new VerifyEmail($user, $code, $verifyUrl, $baseUrl));

            return response()->json(['message' => 'Verification email sent']);
        } catch (\Exception $e) {
            Log::error('Failed to send verification email', ['error' => $e->getMessage(), 'user_id' => $request->user()?->id]);
            return response()->json(['success' => false, 'message' => 'Failed to send verification email'], 500);
        }
    }

    //welcome and verify email function
    public function sendWelcomeAndVerifyEmail($user)
    {
        // Generate 6-char code and set 24h expiry
        $code = strtoupper(Str::random(6));
        $expiresAt = now()->addDay();

        EmailVerification::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => $expiresAt,
        ]);

        // Build frontend verify URL with code only for security
        $verifyUrl = config('app.frontend_url', env('FRONTEND_URL')) .
            '/verify-email?code=' . $code;
        $baseUrl = config('app.frontend_url', env('FRONTEND_URL')) . '/verify-email';

        Mail::to($user->email)
            ->send(new WelomeAndVerifyEmail($user, $code, $verifyUrl, $baseUrl));
    }

    /**
     * Verify Email Address
     *
     * Consumes a verification code to confirm the user's email address.
     * Upon success, marks the email as verified and returns an authentication token for immediate login.
     * The code is one-time use and expires in 24 hours.
     *
     * @bodyParam code string required The 6-character verification code from the email. Example: ABC123
     *
     * @response 200 {
     *   "message": "Email verified",
     *   "token": "2|zIF5K7csJqxfM9...",
     *   "user": {
     *     "id": 2,
     *     "username": "jane_doe",
     *     "email": "jane.doe@example.com",
     *     "email_verified_at": "2025-10-24T12:00:00.000000Z"
     *   }
     * }
     * @response 400 {
     *   "message": "Email already verified"
     * }
     * @response 422 {
     *   "message": "Invalid or expired code"
     * }
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $verification = EmailVerification::where('code', $validated['code'])
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (!$verification || $verification->isExpired()) {
            return response()->json(['message' => 'Invalid or expired code'], 422);
        }

        $user = $verification->user;

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email already verified'], 400);
        }

        // Mark code as consumed and verify user
        $verification->consumed_at = now();
        $verification->save();

        $user->email_verified_at = now();
        $user->save();

        // Create a new token for auto-login
        $token = $user->createToken('email-verification')->plainTextToken;

        return response()->json([
            'message' => 'Email verified',
            'token' => $token,
            'user' => $user,
        ]);
    }


}
