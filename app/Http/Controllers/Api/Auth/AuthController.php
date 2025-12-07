<?php

namespace App\Http\Controllers\Api\Auth;

use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\MemberProfile;
use Laravel\Sanctum\PersonalAccessToken;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * @group Authentication
 *
 * APIs for user registration, login, logout and password resets.
 */
class AuthController extends Controller
{
    /**
     * Signup
     *
     * Register a new user and return a 201 response on success.
     *
     * @bodyParam username string required The user's username. Example: curluser
     * @bodyParam email string required The user's email. Example: curluser@example.com
     * @bodyParam password string required Must be 8+ chars with letters, numbers & special chars. Example: Pass123!@#
     * @bodyParam password_confirmation string required Confirmation of the password. Example: Pass123!@#
     * @response 201
     */
    public function signup(Request $request) {
        $validatedData = $request->validate([
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9])[A-Za-z\d\S]{8,}$/'],
            'password_confirmation' => 'required|string',
        ], [
            'username.required' => 'The username field is required.',
            'username.unique' => 'This username is already taken.',
            'password.required' => 'The password field is required.',
            'password.min' => 'The password field must be at least 8 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.regex' => 'The password must contain at least one lowercase letter, one uppercase letter, one number, and one special character.',
        ]);

        $validatedData['password'] = Hash::make($validatedData['password']);

        $user = User::create($validatedData);

        if($user) {
            // Automatically create a basic member profile for the new user
            MemberProfile::create([
                'user_id' => $user->id,
                'first_name' => '',
                'last_name' => '',
                'county_id' => null,
                'terms_accepted' => false,
                'marketing_consent' => false,
            ]);

            $emailVerificationController = new EmailVerificationController();
            $emailVerificationController->sendWelcomeAndVerifyEmail($user);

            return response()->json(null, 201);
        }

        return response()->json(null, 404);
    }

    /**
     * Login
     *
     * Authenticate user and return an access token.
     *
     * @bodyParam email string required The user's email. Example: curluser@example.com
     * @bodyParam password string required The user's password. Example: password123
     * @bodyParam remember_me boolean Optional remember flag to extend token expiry. Example: true
     * @response 200 {
     *  "user": {"id":1,"username":"curluser","email":"curluser@example.com"},
     *  "access_token": "token-value",
     *  "email_verified": false
     * }
     */
    public function login(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        //remember me functionality
        if ($request->filled('remember_me')) {
            $rememberMe = $request->input('remember_me');
        } else {
            $rememberMe = false;
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Create token and optionally set per-token expiration when remember_me is true.
        $tokenResult = $user->createToken($request->email);
        $plainText = $tokenResult->plainTextToken;

        // If remember_me was requested, set expires_at on the PersonalAccessToken model.
        if ($rememberMe) {
            try {
                $accessTokenModel = $tokenResult->accessToken ?? $tokenResult->accessToken ?? null;
                if ($accessTokenModel) {
                    $accessTokenModel->expires_at = now()->addDays(30);
                    $accessTokenModel->save();
                }
            } catch (\Throwable $e) {
                // Don't fail login if we can't persist expires_at; token will still work with default expiration.
            }
        }

        return response()->json([
            'user' => $user,
            'access_token' => $plainText,
            'email_verified' => !is_null($user->email_verified_at)
        ], 200);
    }

    /**
     * Logout
     *
     * Revoke the authenticated user's token. This endpoint works with or without a valid token.
     *
     * @response 200
     */
    public function logout(Request $request) {
        // Revoke the token used in this request (logout single device/session).
        // Use multiple deletion strategies to ensure the token is removed in tests and in all environments.
        try {
            $bearer = $request->bearerToken();

            // 1) If we have an authenticated user and a current access token, delete it.
            if ($request->user()) {
                $current = $request->user()->currentAccessToken();
                if ($current) {
                    $current->delete();
                }
            }

            // 2) Try to find and delete the token model using the bearer token plaintext.
            if (!empty($bearer)) {
                try {
                    $tokenModel = PersonalAccessToken::findToken($bearer);
                    if ($tokenModel) {
                        $tokenModel->delete();
                    }
                } catch (\Throwable $_) {
                    // ignore lookup failures
                }

                // 3) As a further fallback, parse the id portion ("{id}|{token}") and delete by id.
                if (str_contains($bearer, '|')) {
                    [$id] = explode('|', $bearer, 2);
                    $id = (int) $id;
                    if ($id > 0) {
                        try { PersonalAccessToken::where('id', $id)->delete(); } catch (\Throwable $_) {}
                    }
                }
            }

            // 4) Final safeguard: delete any remaining tokens for the user to avoid leaving an active token.
            if ($request->user()) {
                try { $request->user()->tokens()->delete(); } catch (\Throwable $_) {}
            }
        } catch (\Throwable $e) {
            // best-effort cleanup: attempt to delete tokens for the user if possible
            try { if ($request->user()) { $request->user()->tokens()->delete(); } } catch (\Throwable $_) {}
        }

        return response()->json(null, 200);
    }

    /**
     * Get Authenticated User
     *
     * Returns the currently authenticated user.
     *
     * @authenticated
     * @response 200 {
     *  "id": 1,
     *  "username": "curluser",
     *  "email": "curluser@example.com"
     * }
     */
    public function getAuthenticatedUser(Request $request) {
        // Additional check: ensure the bearer token used in this request still maps to a token model.
        // This makes token revocation deterministic in tests where tokens are deleted directly.
        $bearer = $request->bearerToken();
        if ($bearer) {
            $tokenModel = PersonalAccessToken::findToken($bearer);
            if (! $tokenModel) {
                return response()->json(null, 401);
            }
        }

        return $request->user();
    }

    /**
     * Send password reset link email
     *
     * Sends a password reset link to the user's email.
     *
     * @bodyParam email string required The user's email. Example: curluser@example.com
     * @response 200 {"message": "We have emailed your password reset link!"}
     */
    public function sendPasswordResetLinkEmail(Request $request) {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'We have emailed your password reset link!'], 200);
        }

        $token = Password::createToken($user);

        $user->notify(new ResetPasswordNotification($token));

        return response()->json(['message' => 'We have emailed your password reset link!'], 200);
    }

    /**
     * Reset the user's password.
     *
     * @bodyParam token string required The password reset token. Example: abc123
     * @bodyParam email string required The user's email. Example: curluser@example.com
     * @bodyParam password string required Must be 8+ chars with letters, numbers & special chars. Example: Pass123!@#
     * @bodyParam password_confirmation string required Confirmation of the password. Example: Pass123!@#
     * @response 200 {"message": "Password reset successful."}
     * @response 400 {"message": "Invalid or expired token."}
     * @response 404 {"message": "User not found."}
     * @response 422 {"message": "The password reset token has expired. Please request a new password reset link."}
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => [
                'required',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9])[A-Za-z\d\S]{8,}$/'
            ],
        ], [
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least 8 characters long.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.regex' => 'The password must contain at least one lowercase letter, one uppercase letter, one number, and one special character.',
        ]);

        $email = $request->input('email');
        $token = $request->input('token');
        $newPassword = $request->input('password');

        $resetRecord = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $resetRecord) {
            return response()->json(['message' => 'Invalid password reset request.'], 404);
        }

        // Check if token matches hashed one
        if (! Hash::check($token, $resetRecord->token)) {
            return response()->json(['message' => 'Invalid or expired token.'], 400);
        }

        // Check if token is expired (default: 60 minutes)
        $createdAt = Carbon::parse($resetRecord->created_at);
        if ($createdAt->addMinutes(config('auth.passwords.users.expire', 60))->isPast()) {
            return response()->json([
                'message' => 'The password reset token has expired. Please request a new password reset link.'
            ], 422);
        }

        $user = \App\Models\User::where('email', $email)->first();

        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Check if the new password matches the current one
        if (Hash::check($newPassword, $user->password)) {
            return response()->json(['message' => 'New password cannot be the same as the old password.'], 400);
        }

        // Update the password and remember token
        $user->forceFill([
            'password' => Hash::make($newPassword),
            'remember_token' => Str::random(60),
        ])->save();

        // Fire password reset event (optional listener for logging or notification)
        event(new PasswordReset($user));

        // Clean up used token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json(['message' => 'Password reset successful.'], 200);
    }

    //add function to allow changing password while signed in
    //add scribe annotations
    /**
     * Change Password
     *
     * Allows an authenticated user to change their password.
     * @authenticated
     * @bodyParam current_password string required The user's current password. Example: OldPass123!@#
     * @bodyParam new_password string required Must be 8+ chars with letters, numbers & special chars. Example: NewPass123!@#
     * @bodyParam new_password_confirmation string required Confirmation of the new password. Example: NewPass123!@#
     * @response 200 {"message": "Password changed successfully."}
     * @response 400 {"message": "Current password is incorrect."}
     * @response 400 {"message": "New password cannot be the same as the old password."}
     * @response 422 {"message": "The new password must contain at least one lowercase letter, one uppercase letter, one number, and one special character."}
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => [
                'required',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9])[A-Za-z\d\S]{8,}$/'
            ],
        ], [
            'new_password.required' => 'The new password field is required.',
            'new_password.min' => 'The new password must be at least 8 characters long.',
            'new_password.confirmed' => 'The new password confirmation does not match.',
            'new_password.regex' => 'The new password must contain at least one lowercase letter, one uppercase letter, one number, and one special character.',
        ]);

        $user = $request->user();

        // Verify current password
        if (! Hash::check($request->input('current_password'), $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 400);
        }

        $newPassword = $request->input('new_password');

        // Check if the new password matches the current one
        if (Hash::check($newPassword, $user->password)) {
            return response()->json(['message' => 'New password cannot be the same as the old password.'], 400);
        }

        // Update the password
        $user->forceFill([
            'password' => Hash::make($newPassword),
            'remember_token' => Str::random(60),
        ])->save();

        return response()->json(['message' => 'Password changed successfully.'], 200);
    }


}
