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
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\SubscriptionEnhancement;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * @group Authentication
 * @groupDescription APIs for managing user authentication sessions, including registration, login, logout, and password recovery processes.
 *
 * This group handles the core identity management features of the application.
 * Note: Password security rules enforce at least one uppercase, lowercase, number, and special character.
 */
class AuthController extends Controller
{
    /**
     * Signup / Register
     *
     * Creates a new user account, initializes a basic member profile, and sends an email verification link.
     * This is the entry point for new users to access the platform.
     *
     * @bodyParam username string required The desired username. Must be unique. Example: curluser
     * @bodyParam email string required The user's valid email address. Must be unique. Example: curluser@example.com
     * @bodyParam password string required Password (min 8 chars, mixed case, numbers, special chars). Example: Pass123!@#
     * @bodyParam password_confirmation string required Must match the password field. Example: Pass123!@#
     *
     * @response 201 {
     *  "user": {
     *      "id": 2,
     *      "username": "jane_doe",
     *      "email": "jane.doe@example.com",
     *      "email_verified_at": null,
     *      "ui_permissions": {
     *          "can_view_users": false,
     *          "can_create_users": false,
     *          "can_access_admin_panel": false
     *      },
     *      "admin_access": {
     *          "can_access_admin": false,
     *          "menu": []
     *      },
     *      "member_profile": {
     *          "is_staff": false,
     *          "first_name": "Jane",
     *          "last_name": "Doe"
     *      }
     *  }
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "email": ["The email has already been taken."]
     *   }
     * }
     */
    public function signup(Request $request) {
        $validatedData = $request->validate([
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9])[A-Za-z\d\S]{8,}$/'],
            'password_confirmation' => 'required|string',
            'terms_accepted' => 'required|accepted',
        ], [
            'username.required' => 'The username field is required.',
            'username.unique' => 'This username is already taken.',
            'password.required' => 'The password field is required.',
            'password.min' => 'The password field must be at least 8 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.regex' => 'The password must contain at least one lowercase letter, one uppercase letter, one number, and one special character.',
            'terms_accepted.required' => 'You must accept the Terms & Conditions.',
            'terms_accepted.accepted' => 'You must accept the Terms & Conditions.',
        ]);

        $validatedData['password'] = Hash::make($validatedData['password']);

        // Use a transaction to ensure all related records are created together
        $user = DB::transaction(function () use ($validatedData) {
            $user = User::create($validatedData);

            // Get the default free plan (price = 0)
            $freePlan = Plan::getDefaultFreePlan();
            
            \Log::info('[Signup] User created', [
                'user_id' => $user->id,
                'email' => $user->email,
                'free_plan_found' => $freePlan ? true : false,
                'free_plan_id' => $freePlan?->id,
                'free_plan_name' => $freePlan?->name,
            ]);

            // Automatically create a basic member profile for the new user
            MemberProfile::create([
                'user_id' => $user->id,
                'first_name' => '',
                'last_name' => '',
                'county_id' => null,
                'plan_id' => $freePlan?->id,
                'terms_accepted' => true,
                'marketing_consent' => false,
            ]);

            // Create a free subscription if a free plan exists
            if ($freePlan) {
                $subscription = PlanSubscription::create([
                    'subscriber_id' => $user->id,
                    'subscriber_type' => 'App\Models\User',
                    'plan_id' => $freePlan->id,
                    'name' => $freePlan->name,
                    'slug' => $freePlan->slug . '-' . $user->id . '-' . time(),
                    'starts_at' => now(),
                    'ends_at' => null, // Free plan never expires
                ]);

                \Log::info('[Signup] Subscription created', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                ]);

                // Create subscription enhancement (active status)
                SubscriptionEnhancement::create([
                    'subscription_id' => $subscription->id,
                    'status' => 'active',
                    'max_renewal_attempts' => 0,
                ]);

                // Set user's active subscription
                $user->update([
                    'active_subscription_id' => $subscription->id,
                    'subscription_status' => 'active',
                    'subscription_activated_at' => now(),
                ]);
                
                \Log::info('[Signup] User subscription updated', [
                    'user_id' => $user->id,
                    'active_subscription_id' => $user->active_subscription_id,
                ]);
            } else {
                \Log::warning('[Signup] No free plan found - user has no subscription', [
                    'user_id' => $user->id,
                ]);
            }

            return $user;
        });

        if($user) {
            $emailVerificationController = new EmailVerificationController();
            $emailVerificationController->sendWelcomeAndVerifyEmail($user);

            return response()->json([
                'user' => new \App\Http\Resources\SecureUserResource($user),
            ], 201);
        }

        return response()->json(null, 404);
    }

    /**
     * @bodyParam email string required The registered email address. Example: curluser@example.com
     * @bodyParam password string required The user's password. Example: Pass123!@#
     * @bodyParam remember_me boolean Optional. If true, extends session validity. Example: true
     *
     * @response 200 {
     *  "user": {
     *      "id": 2,
     *      "username": "jane_doe",
     *      "email": "jane.doe@example.com"
     *  },
     *  "email_verified": true
     * }
     * @response 200 {
     *  "requires_2fa": true,
     *  "email": "jane.doe@example.com",
     *  "message": "Two-factor authentication required."
     * }
     * @response 422 {
     *   "message": "The provided credentials are incorrect.",
     *   "errors": {
     *     "email": ["The provided credentials are incorrect."]
     *   }
     * }
     */
    public function login(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $rememberMe = $request->boolean('remember_me', false);

        if (!Auth::attempt($request->only('email', 'password'), $rememberMe)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        // Check if 2FA is enabled
        if ($user->two_factor_enabled) {
            // Logout immediately if 2FA is required, session will be established after TOTP
            Auth::logout();
            return response()->json([
                'requires_2fa' => true,
                'email' => $user->email,
                'message' => 'Two-factor authentication required.',
            ], 200);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Eager load relations for UI hydration
        $user->load(['memberProfile', 'roles']);

        return response()->json([
            'user' => new \App\Http\Resources\SecureUserResource($user),
            'email_verified' => !is_null($user->email_verified_at)
        ], 200);
    }

    /**
     * Logout
     *
     * Invalidate the authenticated user's session.
     *
     * @authenticated
    * @response 200 {
    *   // No content returned on successful logout. HTTP 200 OK with empty body.
    * }
     */
    public function logout(Request $request) {
        if (Auth::user()) {
            Auth::guard('web')->logout();
        }

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ], 200);
    }

    /**
     * Get Authenticated User
     *
     * Retrieve the profile details of the currently logged-in user.
     * Useful for frontend applications to fetch user state on page load.
     *
     * @authenticated
     * @response 200 {
     *  "id": 1,
     *  "username": "curluser",
     *  "email": "curluser@example.com",
     *  "email_verified_at": null,
     *  "created_at": "2023-01-01T12:00:00.000000Z",
     *  "updated_at": "2023-01-01T12:00:00.000000Z"
     * }
     * @response 401 {
     *   "message": "Unauthenticated."
     * }
     */
    public function getAuthenticatedUser(Request $request) {
        return $request->user();
    }

    /**
     * Send Password Reset Link
     *
     * Initiates the password recovery process by sending a reset link to the user's email address.
     * To prevent email enumeration, this endpoint returns a success message even if the email does not exist in the system.
     *
     * @bodyParam email string required The email address to send the reset link to. Example: curluser@example.com
     *
     * @response 200 {
     *   "message": "We have emailed your password reset link!"
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "email": ["The email field is required."]
     *   }
     * }
     */
    public function sendPasswordResetLinkEmail(Request $request) {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'We have emailed your password reset link!'], 200);
        }

        $resetToken = Password::createToken($user);

        $user->notify(new ResetPasswordNotification($resetToken));

        return response()->json(['message' => 'We have emailed your password reset link!'], 200);
    }

    /**
     * Reset Password
     *
     * Finalizes the password recovery process using a valid reset token.
     *
     * @bodyParam token string required The secure token received in the password reset email. Example: c0500732df...
     * @bodyParam email string required The user's email address. Example: curluser@example.com
     * @bodyParam password string required The new password (min 8 chars, mixed case, numbers, special chars). Example: NewPass123!@#
     * @bodyParam password_confirmation string required Must match the password field. Example: NewPass123!@#
     *
     * @response 200 {
     *   "message": "Password reset successful."
     * }
     * @response 400 {
     *   "message": "Invalid or expired token."
     * }
     * @response 404 {
     *   "message": "User not found."
     * }
     * @response 422 {
     *   "message": "The password reset token has expired. Please request a new password reset link."
     * }
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

        $user = User::where('email', $email)->first();

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
     * Allows a currently logged-in user to update their password.
     * Requires the current password for security verification.
     *
     * @authenticated
     * @bodyParam current_password string required The user's existing password. Example: OldPass123!@#
     * @bodyParam new_password string required The new password to set (min 8 chars, mixed case, numbers, special chars). Example: NewPass123!@#
     * @bodyParam new_password_confirmation string required Must match the new password. Example: NewPass123!@#
     *
     * @response 200 {
     *   "message": "Password changed successfully."
     * }
     * @response 400 {
     *   "message": "Current password is incorrect."
     * }
     * @response 422 {
     *   "message": "The new password must contain at least one lowercase letter, one uppercase letter, one number, and one special character."
     * }
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


    /**
     * Get Current User (UI Optimized)
     *
     * Returns the authenticated user with specific UI permission flags.
     * Use this endpoint for initial session hydration and authorization checks.
     *
     * @authenticated
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load(['memberProfile', 'roles']);
        
        return new \App\Http\Resources\SecureUserResource($user);
    }
}
