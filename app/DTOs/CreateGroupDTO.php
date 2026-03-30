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
        public ?int $county_id = null,
        public ?int $forum_tag_id = null,
        public ?string $description = null,
        public ?string $slug = null,
        public bool $is_active = true,
        public bool $is_private = false,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim($data['name']),
            county_id: isset($data['county_id']) ? (int) $data['county_id'] : null,
            forum_tag_id: isset($data['forum_tag_id']) ? (int) $data['forum_tag_id'] : null,
            description: $data['description'] ?? null,
            slug: $data['slug'] ?? null,
            is_active: $data['is_active'] ?? true,
            is_private: $data['is_private'] ?? false,
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
            'forum_tag_id' => $this->forum_tag_id,
            'description' => $this->description,
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'is_private' => $this->is_private,
        ];
    }
}
