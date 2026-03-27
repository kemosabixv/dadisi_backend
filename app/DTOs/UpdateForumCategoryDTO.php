<?php

namespace App\DTOs;

/**
 * Update Forum Category DTO
 *
 * Data Transfer Object for forum category update operations.
 */
class UpdateForumCategoryDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?int $parent_id = null,
        public ?string $icon = null,
        public ?string $color = null,
        public ?int $order = null,
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
            parent_id: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            icon: $data['icon'] ?? null,
            color: $data['color'] ?? null,
            order: isset($data['order']) ? (int) $data['order'] : null,
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
            'parent_id' => $this->parent_id,
            'icon' => $this->icon,
            'color' => $this->color,
            'order' => $this->order,
            'is_active' => $this->is_active,
        ], fn($v) => $v !== null);
    }
}
