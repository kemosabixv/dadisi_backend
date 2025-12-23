<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Models\MemberProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @group Authentication
 * @subgroup Social Authentication (Google)
 *
 * One-tap and OAuth flows for signing in with Google.
 */
class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth
     *
     * Initiates the Google OAuth2 flow by redirecting the user to Google's consent page.
     * Use this for starting the login process from the frontend.
     *
     * @unauthenticated
     */
    public function redirectToGoogle()
    {
        /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
        $driver = Socialite::driver('google');

        // Always show account picker to prevent popup suppression after first login
        return $driver
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /**
     * Handle Google OAuth callback
     *
     * The callback endpoint that Google redirects back to after user consent.
     * This method handles account creation or linking and redirects to the frontend with an authentication token.
     *
     * @unauthenticated
     */
    public function handleGoogleCallbackApi(Request $request)
    {
        $googleUser = Socialite::driver('google')->user();

        $email = $googleUser->getEmail();

        // Check if user exists by email
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            // User exists - handle account linking
            if ($existingUser->password && !$existingUser->google_id) {
                // Existing email/password user wants to link Google
                // SECURITY: Only allow linking if email is verified to prevent account takeover
                if (!$existingUser->email_verified_at) {
                    Log::info("google sso without verification");
                    return redirect(config('app.frontend_url') . '/oauth/callback#error=email_verification_required');
                }

                // Email is verified - allow Google linking
                $existingUser->update([
                    'google_id' => $googleUser->getId(),
                ]);
                $user = $existingUser;
            } elseif ($existingUser->google_id) {
                // Existing Google user - just login
                $user = $existingUser;
            } else {
                // Edge case: has no password and no Google ID (shouldn't happen)
                return redirect(config('app.frontend_url') . '/oauth/callback#error=invalid_account');
            }
        } else {
            // No user with this email - create new Google user

            // Generate unique random username: word + 4 numbers
            $words = ['swift', 'eagle', 'falcon', 'hawk', 'raven', 'owl', 'phoenix', 'dragon', 'tiger', 'wolf'];
            do {
                $randomWord = $words[array_rand($words)];
                $randomNumbers = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $generatedUsername = $randomWord . $randomNumbers;
            } while (User::where('username', $generatedUsername)->exists());

            // Split Google name into first and last name
            $fullName = $googleUser->getName();
            $nameParts = explode(' ', trim($fullName), 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';

            $user = User::create([
                'username' => $generatedUsername,
                'email' => $email,
                'google_id' => $googleUser->getId(),
                'password' => bcrypt(str()->random(12)),
                'email_verified_at' => now(),
            ]);

            // Create member profile with split names
            MemberProfile::create([
                'user_id' => $user->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'county_id' => 1, // Default to first county
                'terms_accepted' => false,
                'marketing_consent' => false,
            ]);
        }

        // Create token like in AuthController
        $tokenResult = $user->createToken($user->email);
        $plainText = $tokenResult->plainTextToken;

        $frontendUrl = config('app.frontend_url').'/oauth/callback';
        $params = http_build_query([
            'access_token' => $plainText,
            'user' => json_encode($user),
            'email_verified' => !is_null($user->email_verified_at)
        ]);

        return redirect($frontendUrl . '#' . $params);
    }
}
