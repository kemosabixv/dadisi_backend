<?php

namespace App\DTOs;

/**
 * Create Post DTO
 *
 * Data Transfer Object for blog post creation operations.
 */
class CreatePostDTO
{
    public function __construct(
        public string $title,
        public string $body,
        public int $user_id,
        public int $county_id,
        public ?string $excerpt = null,
        public ?string $hero_image_path = null,
        public string $status = 'draft',
        public ?string $meta_title = null,
        public ?string $meta_description = null,
        public bool $is_featured = false,
        public bool $allow_comments = true,
        public ?\DateTime $published_at = null,
        public ?array $category_ids = null,
        public ?array $tag_ids = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            body: $data['body'],
            user_id: (int) $data['user_id'],
            county_id: (int) $data['county_id'],
            excerpt: $data['excerpt'] ?? null,
            hero_image_path: $data['hero_image_path'] ?? null,
            status: $data['status'] ?? 'draft',
            meta_title: $data['meta_title'] ?? null,
            meta_description: $data['meta_description'] ?? null,
            is_featured: $data['is_featured'] ?? false,
            allow_comments: $data['allow_comments'] ?? true,
            published_at: isset($data['published_at']) ? new \DateTime($data['published_at']) : null,
            category_ids: $data['category_ids'] ?? null,
            tag_ids: $data['tag_ids'] ?? null,
        );
    }

    /**
     * Convert DTO to array for model creation
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'user_id' => $this->user_id,
            'county_id' => $this->county_id,
            'excerpt' => $this->excerpt,
            'hero_image_path' => $this->hero_image_path,
            'status' => $this->status,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'is_featured' => $this->is_featured,
            'allow_comments' => $this->allow_comments,
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
            'category_ids' => $this->category_ids,
            'tag_ids' => $this->tag_ids,
        ];
    }
}
