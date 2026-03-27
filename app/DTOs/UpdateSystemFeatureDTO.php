<?php

namespace App\DTOs;

/**
 * Update System Feature DTO
 *
 * Data Transfer Object for system feature update operations.
 */
class UpdateSystemFeatureDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?string $default_value = null,
        public ?bool $is_active = null,
        public ?int $sort_order = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            default_value: $data['default_value'] ?? null,
            is_active: isset($data['is_active']) ? (bool) $data['is_active'] : null,
            sort_order: isset($data['sort_order']) ? (int) $data['sort_order'] : null,
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

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->default_value !== null) {
            $data['default_value'] = $this->default_value;
        }

        if ($this->is_active !== null) {
            $data['is_active'] = $this->is_active;
        }

        if ($this->sort_order !== null) {
            $data['sort_order'] = $this->sort_order;
        }

        return $data;
    }
}
