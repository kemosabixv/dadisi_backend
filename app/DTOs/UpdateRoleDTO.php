<?php

namespace App\DTOs;

/**
 * Update Role DTO
 *
 * Data Transfer Object for role update operations.
 */
class UpdateRoleDTO
{
    public function __construct(
        public ?string $name = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? trim($data['name']) : null,
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

        return $data;
    }
}
