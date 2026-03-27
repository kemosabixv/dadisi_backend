<?php

namespace App\Services\Profile;

use App\Exceptions\UserException;
use App\Models\User;
use App\Models\SystemSetting;
use App\Services\Contracts\PublicProfileServiceContract;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * PublicProfileService
 *
 * Handles public profile operations.
 *
 * @package App\Services\Profile
 */
class PublicProfileService implements PublicProfileServiceContract
{
    /**
     * Get public profile by username
     *
     * @param string $username The username to look up
     * @param User|null $requestUser The user making the request
     * @return PublicProfileDTO Public profile DTO
     *
     * @throws UserException If user not found, or is a super_admin
     */
    public function getPublicProfile(string $username, ?User $requestUser = null): PublicProfileDTO
    {
        try {
            $user = User::where('username', $username)
                ->whereNotNull('email_verified_at')
                ->with([
                    'memberProfile:id,user_id,first_name,last_name,date_of_birth,county_id,bio,interests,occupation,public_bio,public_profile_enabled,display_full_name,display_age,prefix,public_role,experience,experience_visible,education,education_visible,skills,skills_visible,achievements,achievements_visible,certifications,certifications_visible,show_email,show_location,show_join_date,show_post_count,show_interests,show_occupation',
                    'memberProfile.county:id,name'
                ])
                ->withCount(['forumThreads', 'forumPosts'])
                ->first();

            if (!$user) {
                throw UserException::notFound($username);
            }

            // Block access if the user is a super_admin
            if ($user->hasRole('super_admin')) {
                throw UserException::unauthorized('view this profile');
            }

            $profile = $user->memberProfile;

            // Check if profile is enabled or if requesting user is Admin/Owner
            $isOwner = $requestUser && $requestUser->id === $user->id;
            $isAdmin = $requestUser && \App\Support\AdminAccessResolver::canAccessAdmin($requestUser);

            if (!$isOwner && !$isAdmin && (!$profile || !$profile->public_profile_enabled)) {
                throw UserException::unauthorized('view this private profile');
            }

            return PublicProfileDTO::fromModel($user, $isAdmin || $isOwner);
        } catch (UserException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve public profile', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            throw UserException::notFound($username);
        }
    }

    /**
     * Get privacy settings for a user (deprecated)
     */
    public function getPrivacySettings(Authenticatable $user): array
    {
        return [];
    }

    /**
     * Update privacy settings for a user (deprecated)
     */
    public function updatePrivacySettings(Authenticatable $user, array $settings): array
    {
        return [];
    }

    /**
     * Preview own public profile
     */
    public function previewProfile(Authenticatable $user): PublicProfileDTO
    {
        /** @var User $user */
        return $this->getPublicProfile($user->username, $user);
    }

    /**
     * Get a list of community members for the membership page
     */
    public function getCommunityMembers(): array
    {
        $isEnabled = SystemSetting::where('key', 'membership_page_user_list_enabled')->first()?->value ?? false;
        
        if (!$isEnabled) {
            return ['staff' => [], 'members' => []];
        }

        $publicUsers = User::whereNotNull('email_verified_at')
            ->whereHas('memberProfile', function($query) {
                $query->where('public_profile_enabled', true);
            })
            ->whereDoesntHave('roles', function($q) {
                $q->where('name', 'super_admin');
            })
            ->with(['memberProfile:id,user_id,first_name,last_name,prefix,public_role'])
            ->get();

        $staff = [];
        $members = [];

        foreach ($publicUsers as $user) {
            $profile = $user->memberProfile;
            
            // For community list, we can return simplified objects or DTOs
            $userData = (object) [
                'username' => $user->username,
                'profile_picture_url' => $user->profile_picture_url,
                'prefix' => $profile->prefix,
                'public_role' => $profile->public_role,
            ];

            if ($profile->user->canAccessAdminPanel()) {
                $staff[] = $userData;
            } else {
                $members[] = $userData;
            }
        }

        return [
            'staff' => $staff,
            'members' => $members
        ];
    }
}
