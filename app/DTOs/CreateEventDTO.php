<?php

namespace App\DTOs;

use App\Models\Event;
use Illuminate\Support\Collection;

class CreateEventDTO
{
    public function __construct(
        public string $title,
        public string $description,
        public int $category_id,
        public int $county_id,
        public \DateTime $starts_at,
        public \DateTime $ends_at,
        public ?string $venue = null,
        public ?int $capacity = null,
        public bool $is_online = false,
        public ?string $online_link = null,
        public ?string $image_path = null,
        public bool $featured = false,
        public ?\DateTime $featured_until = null,
        public ?float $price = null,
        public string $currency = 'KES',
        public bool $waitlist_enabled = false,
        public ?int $waitlist_capacity = null,
        public ?string $status = null,
        public ?array $tag_ids = null,
        public ?array $tickets = null,
        public ?array $speakers = null,
        public ?int $featured_media_id = null,
        public ?array $gallery_media_ids = null,
    ) {}

    /**
     * Create from FormRequest validated data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'],
            category_id: $data['category_id'],
            county_id: $data['county_id'],
            starts_at: new \DateTime($data['starts_at']),
            ends_at: new \DateTime($data['ends_at']),
            venue: $data['venue'] ?? null,
            capacity: $data['capacity'] ?? null,
            is_online: $data['is_online'] ?? false,
            online_link: $data['online_link'] ?? null,
            image_path: $data['image_path'] ?? null,
            featured: $data['featured'] ?? false,
            featured_until: isset($data['featured_until']) ? new \DateTime($data['featured_until']) : null,
            price: isset($data['price']) ? (float)$data['price'] : null,
            currency: $data['currency'] ?? 'KES',
            waitlist_enabled: $data['waitlist_enabled'] ?? false,
            waitlist_capacity: $data['waitlist_capacity'] ?? null,
            status: $data['status'] ?? null,
            tag_ids: $data['tag_ids'] ?? null,
            tickets: $data['tickets'] ?? null,
            speakers: $data['speakers'] ?? null,
            featured_media_id: $data['featured_media_id'] ?? null,
            gallery_media_ids: $data['gallery_media_ids'] ?? null,
        );
    }

    /**
     * Convert to array for model creation
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'county_id' => $this->county_id,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'venue' => $this->venue,
            'capacity' => $this->capacity,
            'is_online' => $this->is_online,
            'online_link' => $this->online_link,
            'image_path' => $this->image_path,
            'featured' => $this->featured,
            'featured_until' => $this->featured_until,
            'price' => $this->price,
            'currency' => $this->currency,
            'waitlist_enabled' => $this->waitlist_enabled,
            'waitlist_capacity' => $this->waitlist_capacity,
            'status' => $this->status,
            'tag_ids' => $this->tag_ids,
            'tickets' => $this->tickets,
            'speakers' => $this->speakers,
            'featured_media_id' => $this->featured_media_id,
            'gallery_media_ids' => $this->gallery_media_ids,
        ];
    }
}
