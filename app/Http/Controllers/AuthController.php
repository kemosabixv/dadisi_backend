<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->only(['name', 'email', 'password']);

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            // Explicitly hash password for clarity
            'password' => Hash::make($data['password']),
        ]);

        $abilities = $request->input('abilities', ['*']);
        $remember = $request->boolean('remember', false);

        $tokenName = $remember ? 'api-token-remember' : 'api-token';

        // Create token with requested abilities/scopes
        $tokenResult = $user->createToken($tokenName, $abilities);

        // If remember requested, set a longer expiry on the token record
        if ($remember) {
            $tokenModel = $tokenResult->accessToken ?? $tokenResult->token ?? null;
            // Laravel Sanctum returns a PersonalAccessToken model via accessToken in some versions
            if ($tokenModel) {
                $tokenModel->expires_at = Carbon::now()->addWeeks(4);
                $tokenModel->save();
            }
        }

        $token = $tokenResult->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->only(['email', 'password']);

        $validator = Validator::make($credentials, [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $abilities = $request->input('abilities', ['*']);
        $remember = $request->boolean('remember', false);
        $tokenName = $remember ? 'api-token-remember' : 'api-token';

        $tokenResult = $user->createToken($tokenName, $abilities);

        return response()->json(['user' => $user, 'token' => $tokenResult->plainTextToken]);
    }

    public function logout(Request $request)
    {
        // Try to revoke by the bearer token string first
        $bearer = $request->bearerToken();
        if ($bearer) {
            $tokenModel = PersonalAccessToken::findToken($bearer);
            if ($tokenModel) {
                $tokenModel->delete();
                return response()->json(['message' => 'Logged out']);
            }
        }

        // Fallback: delete current access token model or all tokens for the user
        $user = $request->user();
        if ($user) {
            if ($user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            } else {
                $user->tokens()->delete();
            }
        }

        return response()->json(['message' => 'Logged out']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}
