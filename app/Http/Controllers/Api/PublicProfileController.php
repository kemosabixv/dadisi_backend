<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UserException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdatePrivacySettingsRequest;
use App\Services\Contracts\PublicProfileServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @group Public Profiles
 *
 * APIs for viewing and managing public member profiles and privacy settings.
 */
class PublicProfileController extends Controller
{
    public function __construct(
        private PublicProfileServiceContract $profileService
    ) {
        $this->middleware('auth:sanctum')->only([
            'getPrivacySettings',
            'updatePrivacySettings',
            'preview'
        ]);
    }

    /**
     * View Public Profile
     *
     * Retrieves the public profile for a member by username.
     * Only shows information the user has opted to make public.
     *
     * @urlParam username string required The username of the member to view. Example: jane_doe
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 2,
     *     "username": "jane_doe",
     *     "profile_picture_url": "https://...",
     *     "joined_at": "2025-01-15T10:30:00.000000Z",
     *     "thread_count": 12,
     *     "post_count": 45,
     *     "bio": "Graduate researcher in Genomics interested in drought-resistant crops.",
     *     "location": "Nairobi",
     *     "interests": ["Genomics", "Sustainability"],
     *     "occupation": "Research assistant"
     *   }
     * }
     * @response 404 {"success": false, "message": "User not found."}
     * @response 403 {"success": false, "message": "This profile is private."}
     */
    public function show(string $username): JsonResponse
    {
        try {
            $profileData = $this->profileService->getPublicProfile($username);

            return response()->json([
                'success' => true,
                'data' => $profileData,
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
                'username' => $username,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile',
            ], 500);
        }
    }

    /**
     * Get Privacy Settings
     *
     * Retrieves the authenticated user's current privacy settings.
     *
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "public_profile_enabled": true,
     *     "public_bio": null,
     *     "show_email": false,
     *     "show_location": true,
     *     "show_join_date": true,
     *     "show_post_count": true,
     *     "show_interests": true,
     *     "show_occupation": false
     *   }
     * }
     */
    public function getPrivacySettings(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $settings = $this->profileService->getPrivacySettings($user);

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get privacy settings', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);
            

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch privacy settings',
            ], 500);
        }
    }

    /**
     * Update Privacy Settings
     *
     * Updates the authenticated user's privacy settings.
     *
     * @authenticated
     *
     * @bodyParam public_profile_enabled boolean Enable/disable public profile.
     * @bodyParam public_bio string Public bio text (max 500 chars).
     * @bodyParam show_email boolean Show email on public profile.
     * @bodyParam show_location boolean Show location on public profile.
     * @bodyParam show_join_date boolean Show join date on public profile.
     * @bodyParam show_post_count boolean Show post count on public profile.
     * @bodyParam show_interests boolean Show interests on public profile.
     * @bodyParam show_occupation boolean Show occupation on public profile.
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "public_profile_enabled": true,
     *     "public_bio": "Researcher in genomics",
     *     "show_email": false,
     *     "show_location": true,
     *     "show_join_date": true,
     *     "show_post_count": true,
     *     "show_interests": true,
     *     "show_occupation": true
     *   },
     *   "message": "Privacy settings updated successfully."
     * }
     * @response 400 {"success": false, "message": "Please complete your profile first."}
     */
    public function updatePrivacySettings(UpdatePrivacySettingsRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $settings = $this->profileService->updatePrivacySettings($user, $validated);

            return response()->json([
                'success' => true,
                'data' => $settings,
                'message' => 'Privacy settings updated successfully.',
            ]);
        } catch (UserException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        } catch (\Exception $e) {
            Log::error('Failed to update privacy settings', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);


            return response()->json([
                'success' => false,
                'message' => 'Failed to update privacy settings',
            ], 500);
        }
    }

    /**
     * Preview Own Public Profile
     *
     * Shows how the authenticated user's profile appears to other users.
     *
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 2,
     *     "username": "jane_doe",
     *     "profile_picture_url": "https://...",
     *     "joined_at": "2025-01-15T10:30:00.000000Z",
     *     "bio": "My public bio"
     *   }
     * }
     */
    public function preview(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $profileData = $this->profileService->previewProfile($user);

            return response()->json([
                'success' => true,
                'data' => $profileData,
            ]);
        } catch (UserException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 404);
        } catch (\Exception $e) {
            Log::error('Failed to preview profile', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to preview profile',
            ], 500);
        }
    }
}
