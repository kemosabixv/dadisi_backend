<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UserException;
use App\Http\Controllers\Controller;
use App\Services\Contracts\PublicProfileServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @group Public Profiles
 *
 * APIs for viewing public member profiles.
 */
class PublicProfileController extends Controller
{
    public function __construct(
        private PublicProfileServiceContract $profileService
    ) {
    }

    /**
     * View Public Profile
     *
     * Retrieves the public profile for a member by username.
     *
     * @param \App\Http\Requests\GetPublicProfileRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(\App\Http\Requests\GetPublicProfileRequest $request): JsonResponse
    {
        try {
            $profileDTO = $this->profileService->getPublicProfile(
                $request->validated('username'),
                $request->user('sanctum')
            );

            return response()->json([
                'success' => true,
                'data' => new \App\Http\Resources\PublicProfileResource($profileDTO),
            ]);
        } catch (UserException $e) {
            $statusCode = $e->getCode() ?: 404;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve public profile', [
                'error' => $e->getMessage(),
                'username' => $request->route('username'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile',
            ], 500);
        }
    }

    /**
     * View Community Members
     *
     * Retrieves a list of public members for the membership page, grouped by staff and members.
     * Only works if the setting 'membership_page_user_list_enabled' is true.
     */
    public function community(): JsonResponse
    {
        try {
            $data = $this->profileService->getCommunityMembers();

            return response()->json([
                'success' => true,
                'data' => [
                    'staff' => \App\Http\Resources\CommunityMemberResource::collection($data['staff']),
                    'members' => \App\Http\Resources\CommunityMemberResource::collection($data['members']),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve community member list', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve community members',
            ], 500);
        }
    }
}
