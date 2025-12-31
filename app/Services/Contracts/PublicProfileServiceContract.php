<?php

namespace App\Services\Contracts;

use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * PublicProfileServiceContract
 *
 * Defines the contract for public profile management including
 * privacy settings and profile visibility.
 *
 * @package App\Services\Contracts
 */
interface PublicProfileServiceContract
{
    /**
     * Get public profile by username
     *
     * @param string $username The username to look up
     * @return array Public profile data
     *
     * @throws \App\Exceptions\UserException If user not found or profile is private
     */
    public function getPublicProfile(string $username): array;

    /**
     * Get privacy settings for a user
     *
     * @param Authenticatable $user The user
     * @return array Privacy settings
     */
    public function getPrivacySettings(Authenticatable $user): array;

    /**
     * Update privacy settings for a user
     *
     * @param Authenticatable $user The user
     * @param array $settings Privacy settings to update
     * @return array Updated privacy settings
     *
     * @throws \App\Exceptions\UserException If profile doesn't exist
     */
    public function updatePrivacySettings(Authenticatable $user, array $settings): array;

    /**
     * Preview own public profile
     *
     * @param Authenticatable $user The user
     * @return array Public profile data as seen by others
     */
    public function previewProfile(Authenticatable $user): array;
}
