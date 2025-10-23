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
use Laravel\Sanctum\PersonalAccessToken;

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
	 * @bodyParam name string required The user's full name. Example: Curl User
	 * @bodyParam email string required The user's email. Example: curluser@example.com
	 * @bodyParam password string required Must be 8+ chars with letters, numbers & special chars. Example: Pass123!@#
	 * @response 201
	 */
	public function signup(Request $request) {
		$validatedData = $request->validate([
			'name' => 'required|string|max:255',
			'email' => 'required|email|unique:users,email',
			'password' => ['required', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/'],
		]);

		$validatedData['password'] = Hash::make($validatedData['password']);

		if(User::create($validatedData)) {
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
	 *  "user": {"id":1,"email":"curluser@example.com"},
	 *  "access_token": "token-value"
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
			'access_token' => $plainText
		], 200);
	}

	/**
	 * Logout
	 *
	 * Revoke the authenticated user's token.
	 *
	 * @authenticated
	 * @response 200
	 */
	public function logout(Request $request) {


		// Revoke only the token used in this request (logout single device/session)
		try {
			$current = $request->user()->currentAccessToken();
			if ($current) {
				$current->delete();
			} else {
				// Fallback: try to locate via bearer token
				$bearer = $request->bearerToken();
				if ($bearer) {
					$tokenModel = PersonalAccessToken::findToken($bearer);
					if ($tokenModel) {
						$tokenModel->delete();
					}
				} else {
					// Last resort: delete all tokens for user
					$request->user()->tokens()->delete();
				}
			}
		} catch (\Throwable $e) {
			// If deletion fails, attempt to delete all tokens for the user to avoid leaving an active token
			try { $request->user()->tokens()->delete(); } catch (\Throwable $_) {}
		}

		// Additional safeguard: if a bearer token is present but we couldn't find the model via currentAccessToken()/findToken,
		// attempt to parse the token id from the plain token (format "{id}|{token}") and delete by id.
		try {
			$bearer = $request->bearerToken();
			if ($bearer && str_contains($bearer, '|')) {
				[$id, $rest] = explode('|', $bearer, 2);
				$id = (int) $id;
				if ($id > 0) {
					PersonalAccessToken::where('id', $id)->delete();
				}
			}
		} catch (\Throwable $_) {
			// ignore
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
	 *  "email": "curluser@example.com",
	 *  "name": "Curl User"
	 * }
	 */
	public function getAuthenticatedUser(Request $request) {
		return $request->user();
	}

	public function sendPasswordResetLinkEmail(Request $request) {
		$request->validate(['email' => 'required|email']);

		$status = Password::sendResetLink(
			$request->only('email')
		);

		if($status === Password::RESET_LINK_SENT) {
			return response()->json(['message' => __($status)], 200);
		} else {
			throw ValidationException::withMessages([
				'email' => __($status)
			]);
		}
	}

	public function resetPassword(Request $request) {
		$request->validate([
			'token' => 'required',
			'email' => 'required|email',
			'password' => 'required|min:8|confirmed',
		]);

		$status = Password::reset(
			$request->only('email', 'password', 'password_confirmation', 'token'),
			function ($user, $password) use ($request) {
				$user->forceFill([
					'password' => Hash::make($password)
				])->setRememberToken(Str::random(60));

				$user->save();

				event(new PasswordReset($user));
			}
		);

		if($status == Password::PASSWORD_RESET) {
			return response()->json(['message' => __($status)], 200);
		} else {
			throw ValidationException::withMessages([
				'email' => __($status)
			]);
		}
	}
}
