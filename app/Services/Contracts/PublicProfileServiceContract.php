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
     * @param \App\Models\User|null $requestUser The user making the request (for PII hardening)
     * @return \App\Services\Profile\PublicProfileDTO Public profile DTO
     *
     * @throws \App\Exceptions\UserException If user not found or profile is private
     */
    public function getPublicProfile(string $username, ?User $requestUser = null): \App\Services\Profile\PublicProfileDTO;

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
     * @return \App\Services\Profile\PublicProfileDTO Public profile DTO as seen by others
     */
    public function previewProfile(Authenticatable $user): \App\Services\Profile\PublicProfileDTO;

    /**
     * Get a list of community members for the membership page
     *
     * @return array List of members grouped by staff and regular users
     */
    public function getCommunityMembers(): array;
}
