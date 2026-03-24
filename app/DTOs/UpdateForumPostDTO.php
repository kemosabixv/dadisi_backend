<?php

namespace App\DTOs;

/**
 * Update Forum Post DTO
 *
 * Data Transfer Object for forum post update operations.
 */
class UpdateForumPostDTO
{
    public function __construct(
        public ?string $content = null,
    ) {}

    /**
     * Create DTO instance from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            content: $data['content'] ?? null,
        );
    }

    /**
     * Convert to array (filters null values)
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->content !== null) {
            $data['content'] = $this->content;
        }

        return $data;
    }
}
