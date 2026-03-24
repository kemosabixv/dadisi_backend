<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SecureUserResource;
use App\Services\Contracts\UserInvitationServiceContract;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    protected $invitationService;

    public function __construct(UserInvitationServiceContract $invitationService)
    {
        $this->invitationService = $invitationService;
    }

    /**
     * Verify invitation token.
     *
     * @param string $token
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(string $token)
    {
        try {
            $invitation = $this->invitationService->verifyInvitationToken($token);
            return response()->json([
                'email' => $invitation->email,
                'is_valid' => true
            ]);
        } catch (\App\Exceptions\UserException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to verify invitation'], 500);
        }
    }

    /**
     * Accept invitation and create account.
     *
     * @param Request $request
     * @param string $token
     * @return \Illuminate\Http\JsonResponse
     */
    public function accept(Request $request, string $token)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users,username',
            'password' => [
                'required',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9])[A-Za-z\d\S]{8,}$/'
            ],
            'terms_accepted' => 'required|accepted',
        ], [
            'password.regex' => 'The password must contain at least one lowercase letter, one uppercase letter, one number, and one special character.',
        ]);

        try {
            $user = $this->invitationService->acceptInvitation($token, $request->all());
            return response()->json([
                'message' => 'Account created successfully. You can now log in.',
                'user' => new SecureUserResource($user)
            ]);
        } catch (\App\Exceptions\UserException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to accept invitation'], 500);
        }
    }
}
