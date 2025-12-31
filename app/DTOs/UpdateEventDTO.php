<?php

namespace App\DTOs;

class UpdateEventDTO
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?int $category_id = null,
        public ?int $county_id = null,
        public ?\DateTime $starts_at = null,
        public ?\DateTime $ends_at = null,
        public ?string $venue = null,
        public ?int $capacity = null,
        public ?bool $is_online = null,
        public ?string $online_link = null,
        public ?string $image_path = null,
        public ?bool $featured = null,
        public ?\DateTime $featured_until = null,
        public ?float $price = null,
        public ?string $currency = null,
        public ?bool $waitlist_enabled = null,
        public ?int $waitlist_capacity = null,
        public ?array $tag_ids = null,
        public ?array $tickets = null,
        public ?array $speakers = null,
    ) {}

    /**
     * Create from FormRequest validated data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            category_id: $data['category_id'] ?? null,
            county_id: $data['county_id'] ?? null,
            starts_at: isset($data['starts_at']) ? new \DateTime($data['starts_at']) : null,
            ends_at: isset($data['ends_at']) ? new \DateTime($data['ends_at']) : null,
            venue: $data['venue'] ?? null,
            capacity: $data['capacity'] ?? null,
            is_online: $data['is_online'] ?? null,
            online_link: $data['online_link'] ?? null,
            image_path: $data['image_path'] ?? null,
            featured: $data['featured'] ?? null,
            featured_until: isset($data['featured_until']) ? new \DateTime($data['featured_until']) : null,
            price: isset($data['price']) ? (float)$data['price'] : null,
            currency: $data['currency'] ?? null,
            waitlist_enabled: $data['waitlist_enabled'] ?? null,
            waitlist_capacity: $data['waitlist_capacity'] ?? null,
            tag_ids: $data['tag_ids'] ?? null,
            tickets: $data['tickets'] ?? null,
            speakers: $data['speakers'] ?? null,
        );
    }

    /**
     * Convert to array for model update (only non-null fields)
     */
    public function toArray(): array
    {
        $data = [];

        foreach (get_object_vars($this) as $key => $value) {
            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
