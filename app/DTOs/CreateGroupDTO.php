<?php

namespace App\DTOs;

/**
 * Create Group DTO
 *
 * Data Transfer Object for group creation operations.
 */
class CreateGroupDTO
{
    public function __construct(
        public string $name,
        public int $county_id,
        public ?string $description = null,
        public ?string $slug = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim($data['name']),
            county_id: (int) $data['county_id'],
            description: $data['description'] ?? null,
            slug: $data['slug'] ?? null,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'county_id' => $this->county_id,
            'description' => $this->description,
            'slug' => $this->slug,
        ];
    }
}
