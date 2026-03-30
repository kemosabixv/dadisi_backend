<?php

namespace App\DTOs;

use Illuminate\Http\UploadedFile;

/**
 * Create Forum Thread DTO
 *
 * Data Transfer Object for forum thread creation operations.
 */
class CreateForumThreadDTO
{
    public function __construct(
        public string $title,
        public string $content,
        public ?int $category_id = null,
        public ?int $county_id = null,
        public ?int $group_id = null,
        public array $tag_ids = [],
        public bool $is_pinned = false,
        public bool $is_locked = false,
        public ?UploadedFile $image = null,
    ) {}

    /**
     * Create DTO instance from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            content: $data['content'],
            category_id: isset($data['category_id']) ? (int) $data['category_id'] : null,
            county_id: isset($data['county_id']) ? (int) $data['county_id'] : null,
            group_id: isset($data['group_id']) ? (int) $data['group_id'] : null,
            tag_ids: $data['tag_ids'] ?? [],
            is_pinned: (bool) ($data['is_pinned'] ?? false),
            is_locked: (bool) ($data['is_locked'] ?? false),
            image: $data['image'] ?? null,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'category_id' => $this->category_id,
            'county_id' => $this->county_id,
            'group_id' => $this->group_id,
            'tag_ids' => $this->tag_ids,
            'is_pinned' => $this->is_pinned,
            'is_locked' => $this->is_locked,
        ];
    }
}
