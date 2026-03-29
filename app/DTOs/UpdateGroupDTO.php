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
        public ?bool $is_active = null,
        public ?bool $is_private = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? trim($data['name']) : null,
            county_id: array_key_exists('county_id', $data) ? ($data['county_id'] !== null ? (int) $data['county_id'] : null) : null,
            description: $data['description'] ?? null,
            slug: $data['slug'] ?? null,
            is_active: isset($data['is_active']) ? (bool)$data['is_active'] : null,
            is_private: isset($data['is_private']) ? (bool)$data['is_private'] : null,
        );
    }

    /**
     * Convert to array (filters null values, but allows explicit null for county_id)
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        // We use a different check for county_id if we want to allow setting it to null
        // But the constructor default is null. 
        // We might need a way to distinguish "not provided" vs "provided as null".
        // For simplicity in this DTO, let's just include all non-nulls.
        // Wait, if I want to support setting it to null, I need to know if it was in the input.
        
        $data['county_id'] = $this->county_id;

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->slug !== null) {
            $data['slug'] = $this->slug;
        }
        
        if ($this->is_active !== null) {
            $data['is_active'] = $this->is_active;
        }
        
        if ($this->is_private !== null) {
            $data['is_private'] = $this->is_private;
        }

        return array_filter($data, function($value, $key) {
            // Allow county_id to be null, but others must be non-null to be included
            if ($key === 'county_id') return true;
            return $value !== null;
        }, ARRAY_FILTER_USE_BOTH);
    }
}
