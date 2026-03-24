<?php

namespace App\Services\Profile;

use App\Models\User;
use Carbon\Carbon;

/**
 * PublicProfileDTO
 *
 * Encapsulates public profile data for a user.
 */
class PublicProfileDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly ?string $profile_picture_url,
        public readonly string $joined_at,
        public readonly ?string $full_name,
        public readonly ?int $age,
        public readonly ?string $bio,
        public readonly ?string $public_role,
        public readonly ?string $location,
        public readonly ?array $interests,
        public readonly ?string $occupation,
        public readonly ?string $email,
        public readonly ?array $sections,
        public readonly int $thread_count = 0,
        public readonly int $post_count = 0,
        public readonly bool $is_staff = false,
        public readonly bool $show_post_count = false
    ) {}

    /**
     * Factory method to create a DTO from models
     */
    public static function fromModel(User $user, bool $canViewPII = false): self
    {
        $profile = $user->memberProfile;
        
        $fullName = null;
        if ($profile && $profile->display_full_name) {
            $fullName = trim(implode(' ', array_filter([
                $profile->prefix,
                $profile->first_name,
                $profile->last_name
            ])));
        }

        $age = null;
        if ($profile && $profile->display_age && $profile->date_of_birth) {
            $age = Carbon::parse($profile->date_of_birth)->age;
        }

        $sections = [];
        if ($profile) {
            foreach (['experience', 'education', 'skills', 'achievements', 'certifications'] as $section) {
                $visibleKey = $section . '_visible';
                if ($profile->$visibleKey && !empty($profile->$section)) {
                    $sections[$section] = $profile->$section;
                }
            }
        }

        return new self(
            id: $user->id,
            username: $user->username,
            profile_picture_url: $user->profile_picture_url,
            joined_at: $user->created_at->toISOString(),
            full_name: $fullName,
            age: $age,
            bio: $profile ? ($profile->public_bio ?: ($profile->bio ?? null)) : null,
            public_role: $profile?->public_role,
            location: ($profile && $profile->show_location && $profile->county) ? $profile->county->name : null,
            interests: ($profile && $profile->show_interests) ? $profile->interests : null,
            occupation: ($profile && $profile->show_occupation) ? $profile->occupation : null,
            email: ($canViewPII || ($profile && $profile->show_email)) ? $user->email : null,
            sections: $sections,
            thread_count: (int) ($user->forum_threads_count ?? 0),
            post_count: (int) ($user->forum_posts_count ?? 0),
            is_staff: (bool) ($profile?->is_staff ?? false),
            show_post_count: (bool) ($profile?->show_post_count ?? false)
        );
    }
}
