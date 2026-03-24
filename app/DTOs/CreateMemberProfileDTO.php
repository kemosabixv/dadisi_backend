<?php

namespace App\DTOs;

/**
 * Create Member Profile DTO
 *
 * Data Transfer Object for member profile creation operations.
 */
class CreateMemberProfileDTO
{
    public function __construct(
        public int $county_id,
        public ?string $first_name = null,
        public ?string $last_name = null,
        public ?string $phone = null,
        public ?string $gender = null,
        public ?\DateTime $date_of_birth = null,
        public ?string $occupation = null,
        public ?string $membership_type = null,
        public ?string $emergency_contact_name = null,
        public ?string $emergency_contact_phone = null,
        public bool $terms_accepted = false,
        public ?bool $marketing_consent = null,
        public ?array $interests = null,
        public ?string $bio = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            county_id: (int) $data['county_id'],
            first_name: $data['first_name'] ?? null,
            last_name: $data['last_name'] ?? null,
            phone: $data['phone'] ?? null,
            gender: $data['gender'] ?? null,
            date_of_birth: isset($data['date_of_birth']) ? new \DateTime($data['date_of_birth']) : null,
            occupation: $data['occupation'] ?? null,
            membership_type: $data['membership_type'] ?? null,
            emergency_contact_name: $data['emergency_contact_name'] ?? null,
            emergency_contact_phone: $data['emergency_contact_phone'] ?? null,
            terms_accepted: (bool) ($data['terms_accepted'] ?? false),
            marketing_consent: isset($data['marketing_consent']) ? (bool) $data['marketing_consent'] : null,
            interests: $data['interests'] ?? null,
            bio: $data['bio'] ?? null,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'county_id' => $this->county_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'occupation' => $this->occupation,
            'membership_type' => $this->membership_type,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'terms_accepted' => $this->terms_accepted,
            'marketing_consent' => $this->marketing_consent,
            'interests' => $this->interests,
            'bio' => $this->bio,
        ];
    }
}
