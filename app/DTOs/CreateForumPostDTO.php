<?php

namespace App\DTOs;

/**
 * Create Forum Post DTO
 *
 * Data Transfer Object for forum post creation operations.
 */
class CreateForumPostDTO
{
    public function __construct(
        public ?int $thread_id = null,
        public string $content,
        public ?int $parent_id = null,
    ) {}

    /**
     * Create DTO instance from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            thread_id: isset($data['thread_id']) ? (int) $data['thread_id'] : null,
            content: $data['content'],
            parent_id: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'thread_id' => $this->thread_id,
            'content' => $this->content,
            'parent_id' => $this->parent_id,
        ];
    }
}
