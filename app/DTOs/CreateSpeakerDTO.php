<?php

namespace App\DTOs;

/**
 * Create Speaker DTO
 *
 * Data Transfer Object for event speaker creation operations.
 */
class CreateSpeakerDTO
{
    public function __construct(
        public int $event_id,
        public string $name,
        public ?string $email = null,
        public ?string $company = null,
        public ?string $designation = null,
        public ?string $bio = null,
        public ?string $photo_path = null,
        public ?string $website_url = null,
        public ?string $linkedin_url = null,
        public ?int $sort_order = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            event_id: (int) $data['event_id'],
            name: $data['name'],
            email: $data['email'] ?? null,
            company: $data['company'] ?? null,
            designation: $data['designation'] ?? null,
            bio: $data['bio'] ?? null,
            photo_path: $data['photo_path'] ?? null,
            website_url: $data['website_url'] ?? null,
            linkedin_url: $data['linkedin_url'] ?? null,
            sort_order: isset($data['sort_order']) ? (int) $data['sort_order'] : null,
        );
    }

    /**
     * Convert DTO to array for model creation
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->event_id,
            'name' => $this->name,
            'email' => $this->email,
            'company' => $this->company,
            'designation' => $this->designation,
            'bio' => $this->bio,
            'photo_path' => $this->photo_path,
            'website_url' => $this->website_url,
            'linkedin_url' => $this->linkedin_url,
            'sort_order' => $this->sort_order,
        ];
    }
}
