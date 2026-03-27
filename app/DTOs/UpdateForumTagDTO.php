<?php

namespace App\DTOs;

/**
 * Update Forum Tag DTO
 *
 * Data Transfer Object for forum tag update operations.
 */
class UpdateForumTagDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $is_active = null,
    ) {}

    /**
     * Create DTO instance from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            is_active: isset($data['is_active']) ? (bool) $data['is_active'] : null,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
        ], fn($v) => $v !== null);
    }
}
