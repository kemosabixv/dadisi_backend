<?php

namespace App\DTOs;

/**
 * Update Member Profile DTO
 *
 * Data Transfer Object for member profile update operations.
 */
class UpdateMemberProfileDTO
{
    public function __construct(
        public ?string $first_name = null,
        public ?string $last_name = null,
        public ?string $phone_number = null,
        public ?\DateTime $date_of_birth = null,
        public ?string $gender = null,
        public ?int $county_id = null,
        public ?string $sub_county = null,
        public ?string $ward = null,
        public ?array $interests = null,
        public ?string $bio = null,
        public ?string $occupation = null,
        public ?string $emergency_contact_name = null,
        public ?string $emergency_contact_phone = null,
        public ?bool $marketing_consent = null,
        public ?bool $public_profile_enabled = null,
        public ?string $public_bio = null,
        public ?bool $show_email = null,
        public ?bool $show_location = null,
        public ?bool $show_join_date = null,
        public ?bool $show_post_count = null,
        public ?bool $show_interests = null,
        public ?bool $show_occupation = null,
        public ?bool $display_full_name = null,
        public ?bool $display_age = null,
        public ?string $prefix = null,
        public ?string $public_role = null,
        public ?array $experience = null,
        public ?bool $experience_visible = null,
        public ?array $education = null,
        public ?bool $education_visible = null,
        public ?array $skills = null,
        public ?bool $skills_visible = null,
        public ?array $achievements = null,
        public ?bool $achievements_visible = null,
        public ?array $certifications = null,
        public ?bool $certifications_visible = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            first_name: $data['first_name'] ?? null,
            last_name: $data['last_name'] ?? null,
            phone_number: $data['phone_number'] ?? null,
            date_of_birth: isset($data['date_of_birth']) ? new \DateTime($data['date_of_birth']) : null,
            gender: $data['gender'] ?? null,
            county_id: isset($data['county_id']) ? (int) $data['county_id'] : null,
            sub_county: $data['sub_county'] ?? null,
            ward: $data['ward'] ?? null,
            interests: $data['interests'] ?? null,
            bio: $data['bio'] ?? null,
            occupation: $data['occupation'] ?? null,
            emergency_contact_name: $data['emergency_contact_name'] ?? null,
            emergency_contact_phone: $data['emergency_contact_phone'] ?? null,
            marketing_consent: isset($data['marketing_consent']) ? (bool) $data['marketing_consent'] : null,
            public_profile_enabled: isset($data['public_profile_enabled']) ? (bool) $data['public_profile_enabled'] : null,
            public_bio: $data['public_bio'] ?? null,
            show_email: isset($data['show_email']) ? (bool) $data['show_email'] : null,
            show_location: isset($data['show_location']) ? (bool) $data['show_location'] : null,
            show_join_date: isset($data['show_join_date']) ? (bool) $data['show_join_date'] : null,
            show_post_count: isset($data['show_post_count']) ? (bool) $data['show_post_count'] : null,
            show_interests: isset($data['show_interests']) ? (bool) $data['show_interests'] : null,
            show_occupation: isset($data['show_occupation']) ? (bool) $data['show_occupation'] : null,
            display_full_name: isset($data['display_full_name']) ? (bool) $data['display_full_name'] : null,
            display_age: isset($data['display_age']) ? (bool) $data['display_age'] : null,
            prefix: $data['prefix'] ?? null,
            public_role: $data['public_role'] ?? null,
            experience: $data['experience'] ?? null,
            experience_visible: isset($data['experience_visible']) ? (bool) $data['experience_visible'] : null,
            education: $data['education'] ?? null,
            education_visible: isset($data['education_visible']) ? (bool) $data['education_visible'] : null,
            skills: $data['skills'] ?? null,
            skills_visible: isset($data['skills_visible']) ? (bool) $data['skills_visible'] : null,
            achievements: $data['achievements'] ?? null,
            achievements_visible: isset($data['achievements_visible']) ? (bool) $data['achievements_visible'] : null,
            certifications: $data['certifications'] ?? null,
            certifications_visible: isset($data['certifications_visible']) ? (bool) $data['certifications_visible'] : null,
        );
    }

    /**
     * Convert DTO to array, filtering out null values
     */
    public function toArray(): array
    {
        return array_filter([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone_number' => $this->phone_number,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'county_id' => $this->county_id,
            'sub_county' => $this->sub_county,
            'ward' => $this->ward,
            'interests' => $this->interests,
            'bio' => $this->bio,
            'occupation' => $this->occupation,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'marketing_consent' => $this->marketing_consent,
            'public_profile_enabled' => $this->public_profile_enabled,
            'public_bio' => $this->public_bio,
            'show_email' => $this->show_email,
            'show_location' => $this->show_location,
            'show_join_date' => $this->show_join_date,
            'show_post_count' => $this->show_post_count,
            'show_interests' => $this->show_interests,
            'show_occupation' => $this->show_occupation,
            'display_full_name' => $this->display_full_name,
            'display_age' => $this->display_age,
            'prefix' => $this->prefix,
            'public_role' => $this->public_role,
            'experience' => $this->experience,
            'experience_visible' => $this->experience_visible,
            'education' => $this->education,
            'education_visible' => $this->education_visible,
            'skills' => $this->skills,
            'skills_visible' => $this->skills_visible,
            'achievements' => $this->achievements,
            'achievements_visible' => $this->achievements_visible,
            'certifications' => $this->certifications,
            'certifications_visible' => $this->certifications_visible,
        ], fn ($value) => $value !== null);
    }
}
