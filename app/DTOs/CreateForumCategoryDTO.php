<?php

namespace App\DTOs;

/**
 * Create Forum Category DTO
 *
 * Data Transfer Object for forum category creation operations.
 */
class CreateForumCategoryDTO
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?int $parent_id = null,
        public ?string $icon = null,
        public ?string $color = null,
        public int $order = 0,
        public bool $is_active = true,
    ) {}

    /**
     * Create DTO instance from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? null,
            parent_id: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            icon: $data['icon'] ?? null,
            color: $data['color'] ?? null,
            order: (int) ($data['order'] ?? 0),
            is_active: (bool) ($data['is_active'] ?? true),
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'icon' => $this->icon,
            'color' => $this->color,
            'order' => $this->order,
            'is_active' => $this->is_active,
        ];
    }
}
