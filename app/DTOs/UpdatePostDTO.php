<?php

namespace App\DTOs;

/**
 * Update Post DTO
 *
 * Data Transfer Object for blog post update operations.
 */
class UpdatePostDTO
{
    public function __construct(
        public ?string $title = null,
        public ?string $body = null,
        public ?string $excerpt = null,
        public ?string $hero_image_path = null,
        public ?string $status = null,
        public ?string $meta_title = null,
        public ?string $meta_description = null,
        public ?bool $is_featured = null,
        public ?bool $allow_comments = null,
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
            title: $data['title'] ?? null,
            body: $data['body'] ?? null,
            excerpt: $data['excerpt'] ?? null,
            hero_image_path: $data['hero_image_path'] ?? null,
            status: $data['status'] ?? null,
            meta_title: $data['meta_title'] ?? null,
            meta_description: $data['meta_description'] ?? null,
            is_featured: isset($data['is_featured']) ? (bool) $data['is_featured'] : null,
            allow_comments: isset($data['allow_comments']) ? (bool) $data['allow_comments'] : null,
            published_at: isset($data['published_at']) ? new \DateTime($data['published_at']) : null,
            category_ids: $data['category_ids'] ?? null,
            tag_ids: $data['tag_ids'] ?? null,
        );
    }

    /**
     * Convert DTO to array, filtering out null values
     */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'body' => $this->body,
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
        ], fn ($value) => $value !== null);
    }
}
