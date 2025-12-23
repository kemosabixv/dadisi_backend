<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PublicProfileController extends Controller
{
    /**
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
     */
    public function show(string $username): JsonResponse
    {
        $user = User::where('username', $username)
            ->whereNotNull('email_verified_at')
            ->with(['memberProfile:id,user_id,first_name,last_name,county_id,bio,interests,occupation,public_bio,public_profile_enabled,show_email,show_location,show_join_date,show_post_count,show_interests,show_occupation', 'memberProfile.county:id,name'])
            ->withCount(['forumThreads', 'forumPosts'])
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $profile = $user->memberProfile;
        
        // Check if public profile is disabled
        if ($profile && !$profile->public_profile_enabled) {
            return response()->json(['message' => 'This profile is private.'], 403);
        }

        // Build public profile data based on privacy settings
        $publicData = [
            'id' => $user->id,
            'username' => $user->username,
            'profile_picture_url' => $user->profile_picture_url,
        ];

        // Apply privacy settings
        if (!$profile || $profile->show_join_date) {
            $publicData['joined_at'] = $user->created_at->toISOString();
        }

        if (!$profile || $profile->show_post_count) {
            $publicData['thread_count'] = $user->forum_threads_count;
            $publicData['post_count'] = $user->forum_posts_count;
        }

        if ($profile) {
            // Public bio takes precedence over private bio
            $publicData['bio'] = $profile->public_bio ?: ($profile->bio ?? null);
            
            if ($profile->show_location && $profile->county) {
                $publicData['location'] = $profile->county->name;
            }

            if ($profile->show_interests && $profile->interests) {
                $publicData['interests'] = $profile->interests;
            }

            if ($profile->show_occupation && $profile->occupation) {
                $publicData['occupation'] = $profile->occupation;
            }

            if ($profile->show_email) {
                $publicData['email'] = $user->email;
            }
        }

        return response()->json(['data' => $publicData]);
    }

    /**
     * Get own privacy settings.
     * 
     * @group Public Profiles
     * @authenticated
     */
    public function getPrivacySettings(): JsonResponse
    {
        $user = Auth::user();
        $profile = $user->memberProfile;

        if (!$profile) {
            return response()->json([
                'data' => [
                    'public_profile_enabled' => true,
                    'public_bio' => null,
                    'show_email' => false,
                    'show_location' => true,
                    'show_join_date' => true,
                    'show_post_count' => true,
                    'show_interests' => true,
                    'show_occupation' => false,
                ]
            ]);
        }

        return response()->json([
            'data' => [
                'public_profile_enabled' => $profile->public_profile_enabled ?? true,
                'public_bio' => $profile->public_bio,
                'show_email' => $profile->show_email ?? false,
                'show_location' => $profile->show_location ?? true,
                'show_join_date' => $profile->show_join_date ?? true,
                'show_post_count' => $profile->show_post_count ?? true,
                'show_interests' => $profile->show_interests ?? true,
                'show_occupation' => $profile->show_occupation ?? false,
            ]
        ]);
    }

    /**
     * Update own privacy settings.
     * 
     * @group Public Profiles
     * @authenticated
     * 
     * @bodyParam public_profile_enabled boolean Enable/disable public profile.
     * @bodyParam public_bio string Public bio text.
     * @bodyParam show_email boolean Show email on public profile.
     * @bodyParam show_location boolean Show location on public profile.
     * @bodyParam show_join_date boolean Show join date on public profile.
     * @bodyParam show_post_count boolean Show post count on public profile.
     * @bodyParam show_interests boolean Show interests on public profile.
     * @bodyParam show_occupation boolean Show occupation on public profile.
     */
    public function updatePrivacySettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'public_profile_enabled' => 'sometimes|boolean',
            'public_bio' => 'sometimes|nullable|string|max:500',
            'show_email' => 'sometimes|boolean',
            'show_location' => 'sometimes|boolean',
            'show_join_date' => 'sometimes|boolean',
            'show_post_count' => 'sometimes|boolean',
            'show_interests' => 'sometimes|boolean',
            'show_occupation' => 'sometimes|boolean',
        ]);

        $user = Auth::user();
        $profile = $user->memberProfile;

        if (!$profile) {
            return response()->json(['message' => 'Please complete your profile first.'], 400);
        }

        $profile->update($validated);

        return response()->json([
            'data' => [
                'public_profile_enabled' => $profile->public_profile_enabled,
                'public_bio' => $profile->public_bio,
                'show_email' => $profile->show_email,
                'show_location' => $profile->show_location,
                'show_join_date' => $profile->show_join_date,
                'show_post_count' => $profile->show_post_count,
                'show_interests' => $profile->show_interests,
                'show_occupation' => $profile->show_occupation,
            ],
            'message' => 'Privacy settings updated successfully.',
        ]);
    }

    /**
     * Preview own public profile.
     * 
     * @group Public Profiles
     * @authenticated
     */
    public function preview(): JsonResponse
    {
        $user = Auth::user();
        return $this->show($user->username);
    }
}
