<?php

namespace App\DTOs;

/**
 * Update Group DTO
 *
 * Data Transfer Object for group update operations.
 */
class UpdateGroupDTO
{
    public function __construct(
        public ?string $name = null,
        public ?int $county_id = null,
        public ?string $description = null,
        public ?string $slug = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? trim($data['name']) : null,
            county_id: isset($data['county_id']) ? (int) $data['county_id'] : null,
            description: $data['description'] ?? null,
            slug: $data['slug'] ?? null,
        );
    }

    /**
     * Convert to array (filters null values)
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->county_id !== null) {
            $data['county_id'] = $this->county_id;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->slug !== null) {
            $data['slug'] = $this->slug;
        }

        return $data;
    }
}
