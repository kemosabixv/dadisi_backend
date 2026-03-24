<?php

namespace App\DTOs;

/**
 * Create County DTO
 *
 * Data Transfer Object for county creation operations.
 */
class CreateCountyDTO
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
