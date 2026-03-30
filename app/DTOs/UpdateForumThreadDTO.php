<?php

namespace App\DTOs;

/**
 * Update Forum Thread DTO
 *
 * Data Transfer Object for forum thread update operations.
 */
class UpdateForumThreadDTO
{
    public function __construct(
        public ?string $title = null,
        public ?string $content = null,
        public ?int $category_id = null,
        public ?int $county_id = null,
        public ?int $group_id = null,
        public ?array $tag_ids = null,
        public ?int $media_id = null,
        public ?bool $is_pinned = null,
        public ?bool $is_locked = null,
    ) {}

    /**
     * Create DTO instance from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            content: $data['content'] ?? null,
            category_id: isset($data['category_id']) ? (int) $data['category_id'] : null,
            county_id: isset($data['county_id']) ? (int) $data['county_id'] : null,
            group_id: isset($data['group_id']) ? (int) $data['group_id'] : null,
            tag_ids: $data['tag_ids'] ?? null,
            media_id: isset($data['media_id']) ? (int) $data['media_id'] : null,
            is_pinned: isset($data['is_pinned']) ? (bool) $data['is_pinned'] : null,
            is_locked: isset($data['is_locked']) ? (bool) $data['is_locked'] : null,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'content' => $this->content,
            'category_id' => $this->category_id,
            'county_id' => $this->county_id,
            'group_id' => $this->group_id,
            'tag_ids' => $this->tag_ids,
            'media_id' => $this->media_id,
            'is_pinned' => $this->is_pinned,
            'is_locked' => $this->is_locked,
        ], fn($v) => $v !== null);
    }
}
