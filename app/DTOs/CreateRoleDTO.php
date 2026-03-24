<?php

namespace App\DTOs;

/**
 * Create Role DTO
 *
 * Data Transfer Object for role creation operations.
 */
class CreateRoleDTO
{
    public function __construct(
        public string $name,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim($data['name']),
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
