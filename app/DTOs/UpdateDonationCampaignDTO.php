<?php

namespace App\DTOs;

/**
 * Update Donation Campaign DTO
 *
 * Data Transfer Object for donation campaign update operations.
 */
class UpdateDonationCampaignDTO
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $short_description = null,
        public ?float $goal_amount = null,
        public ?float $minimum_amount = null,
        public ?int $county_id = null,
        public ?string $hero_image_path = null,
        public ?int $featured_media_id = null,
        public ?array $gallery_media_ids = null,
        public ?string $status = null,
        public ?\DateTime $starts_at = null,
        public ?\DateTime $ends_at = null,
        public ?\DateTime $published_at = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            short_description: $data['short_description'] ?? null,
            goal_amount: isset($data['goal_amount']) ? (float) $data['goal_amount'] : null,
            minimum_amount: isset($data['minimum_amount']) ? (float) $data['minimum_amount'] : null,
            county_id: isset($data['county_id']) ? (int) $data['county_id'] : null,
            hero_image_path: $data['hero_image_path'] ?? null,
            featured_media_id: isset($data['featured_media_id']) ? (int) $data['featured_media_id'] : null,
            gallery_media_ids: $data['gallery_media_ids'] ?? null,
            status: $data['status'] ?? null,
            starts_at: isset($data['starts_at']) ? new \DateTime($data['starts_at']) : null,
            ends_at: isset($data['ends_at']) ? new \DateTime($data['ends_at']) : null,
            published_at: isset($data['published_at']) ? new \DateTime($data['published_at']) : null,
        );
    }

    /**
     * Convert DTO to array, filtering out null values
     */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'goal_amount' => $this->goal_amount,
            'minimum_amount' => $this->minimum_amount,
            'county_id' => $this->county_id,
            'hero_image_path' => $this->hero_image_path,
            'featured_media_id' => $this->featured_media_id,
            'gallery_media_ids' => $this->gallery_media_ids,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->format('Y-m-d H:i:s'),
            'ends_at' => $this->ends_at?->format('Y-m-d H:i:s'),
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
        ], fn ($value) => $value !== null);
    }
}
