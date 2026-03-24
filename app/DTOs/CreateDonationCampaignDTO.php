<?php

namespace App\DTOs;

/**
 * Create Donation Campaign DTO
 *
 * Data Transfer Object for donation campaign creation operations.
 */
class CreateDonationCampaignDTO
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $short_description = null,
        public float $goal_amount,
        public ?float $minimum_amount = null,
        public ?int $county_id = null,
        public int $created_by,
        public string $currency = 'KES',
        public ?string $hero_image_path = null,
        public ?int $featured_media_id = null,
        public ?array $gallery_media_ids = null,
        public ?string $status = 'draft',
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
            title: $data['title'],
            description: $data['description'],
            short_description: $data['short_description'] ?? null,
            goal_amount: (float) $data['goal_amount'],
            minimum_amount: isset($data['minimum_amount']) ? (float) $data['minimum_amount'] : null,
            county_id: isset($data['county_id']) ? (int) $data['county_id'] : null,
            created_by: (int) $data['created_by'],
            currency: $data['currency'] ?? 'KES',
            hero_image_path: $data['hero_image_path'] ?? null,
            featured_media_id: isset($data['featured_media_id']) ? (int) $data['featured_media_id'] : null,
            gallery_media_ids: $data['gallery_media_ids'] ?? null,
            status: $data['status'] ?? 'draft',
            starts_at: isset($data['starts_at']) ? new \DateTime($data['starts_at']) : null,
            ends_at: isset($data['ends_at']) ? new \DateTime($data['ends_at']) : null,
            published_at: isset($data['published_at']) ? new \DateTime($data['published_at']) : null,
        );
    }

    /**
     * Convert DTO to array for model creation
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'goal_amount' => $this->goal_amount,
            'minimum_amount' => $this->minimum_amount,
            'county_id' => $this->county_id,
            'created_by' => $this->created_by,
            'currency' => $this->currency,
            'hero_image_path' => $this->hero_image_path,
            'featured_media_id' => $this->featured_media_id,
            'gallery_media_ids' => $this->gallery_media_ids,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->format('Y-m-d H:i:s'),
            'ends_at' => $this->ends_at?->format('Y-m-d H:i:s'),
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
        ];
    }
}
