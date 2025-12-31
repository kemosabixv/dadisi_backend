<?php

namespace App\Services\Profile;

use App\Exceptions\UserException;
use App\Models\User;
use App\Services\Contracts\PublicProfileServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * PublicProfileService
 *
 * Handles public profile operations including privacy settings
 * and profile visibility management.
 *
 * @package App\Services\Profile
 */
class PublicProfileService implements PublicProfileServiceContract
{
    /**
     * Default privacy settings
     */
    private const DEFAULT_PRIVACY_SETTINGS = [
        'public_profile_enabled' => true,
        'public_bio' => null,
        'show_email' => false,
        'show_location' => true,
        'show_join_date' => true,
        'show_post_count' => true,
        'show_interests' => true,
        'show_occupation' => false,
    ];

    /**
     * Get public profile by username
     *
     * @param string $username The username to look up
     * @return array Public profile data
     *
     * @throws UserException If user not found or profile is private
     */
    public function getPublicProfile(string $username): array
    {
        try {
            $user = User::where('username', $username)
                ->whereNotNull('email_verified_at')
                ->with([
                    'memberProfile:id,user_id,first_name,last_name,county_id,bio,interests,occupation,public_bio,public_profile_enabled,show_email,show_location,show_join_date,show_post_count,show_interests,show_occupation',
                    'memberProfile.county:id,name'
                ])
                ->withCount(['forumThreads', 'forumPosts'])
                ->first();

            if (!$user) {
                throw UserException::notFound($username);
            }

            $profile = $user->memberProfile;

            // Check if public profile is disabled
            if ($profile && !$profile->public_profile_enabled) {
                throw UserException::profilePrivate();
            }

            return $this->buildPublicProfileData($user, $profile);
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
     * Get privacy settings for a user
     *
     * @param Authenticatable $user The user
     * @return array Privacy settings
     */
    public function getPrivacySettings(Authenticatable $user): array
    {
        /** @var User $user */
        $profile = $user->memberProfile;

        if (!$profile) {
            return self::DEFAULT_PRIVACY_SETTINGS;
        }

        return [
            'public_profile_enabled' => $profile->public_profile_enabled ?? true,
            'public_bio' => $profile->public_bio,
            'show_email' => $profile->show_email ?? false,
            'show_location' => $profile->show_location ?? true,
            'show_join_date' => $profile->show_join_date ?? true,
            'show_post_count' => $profile->show_post_count ?? true,
            'show_interests' => $profile->show_interests ?? true,
            'show_occupation' => $profile->show_occupation ?? false,
        ];
    }

    /**
     * Update privacy settings for a user
     *
     * @param Authenticatable $user The user
     * @param array $settings Privacy settings to update
     * @return array Updated privacy settings
     *
     * @throws UserException If profile doesn't exist
     */
    public function updatePrivacySettings(Authenticatable $user, array $settings): array
    {
        /** @var User $user */
        $profile = $user->memberProfile;

        if (!$profile) {
            throw UserException::profileNotFound();
        }

        // Filter only allowed settings
        $allowedSettings = [
            'public_profile_enabled',
            'public_bio',
            'show_email',
            'show_location',
            'show_join_date',
            'show_post_count',
            'show_interests',
            'show_occupation',
        ];

        $filteredSettings = array_intersect_key($settings, array_flip($allowedSettings));

        if (!empty($filteredSettings)) {
            $profile->update($filteredSettings);
        }

        Log::info('Privacy settings updated', [
            'user_id' => $user->getAuthIdentifier(),
            'settings' => array_keys($filteredSettings),
        ]);

        return [
            'public_profile_enabled' => $profile->public_profile_enabled,
            'public_bio' => $profile->public_bio,
            'show_email' => $profile->show_email,
            'show_location' => $profile->show_location,
            'show_join_date' => $profile->show_join_date,
            'show_post_count' => $profile->show_post_count,
            'show_interests' => $profile->show_interests,
            'show_occupation' => $profile->show_occupation,
        ];
    }

    /**
     * Preview own public profile
     *
     * @param Authenticatable $user The user
     * @return array Public profile data as seen by others
     */
    public function previewProfile(Authenticatable $user): array
    {
        /** @var User $user */
        return $this->getPublicProfile($user->username);
    }

    /**
     * Build public profile data based on privacy settings
     *
     * @param User $user The user
     * @param mixed $profile The member profile (can be null)
     * @return array Public profile data
     */
    private function buildPublicProfileData(User $user, $profile): array
    {
        // Start with base public data (always visible)
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

        return $publicData;
    }
}
