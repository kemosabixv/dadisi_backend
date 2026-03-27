<?php

namespace App\DTOs;

/**
 * Create Lab Space DTO
 *
 * Data Transfer Object for lab space creation operations.
 */
class CreateLabSpaceDTO
{
    public function __construct(
        public string $name,
        public string $type,
        public int $county,
        public ?int $capacity = null,
        public ?string $location = null,
        public ?string $description = null,
        public ?string $image_path = null,
        public ?array $equipment_list = null,
        public ?array $safety_requirements = null,
        public ?string $rules = null,
        public ?float $hourly_rate = null,
        public ?int $opens_at = null,
        public ?int $closes_at = null,
        public ?array $operating_days = null,
        public ?int $slots_per_hour = null,
        public bool $bookings_enabled = true,
        public bool $is_available = true,
        public ?\DateTime $available_from = null,
        public ?\DateTime $available_until = null,
        public ?int $featured_media_id = null,
        public ?array $gallery_media_ids = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: $data['type'],
            county: (int) $data['county'],
            capacity: isset($data['capacity']) ? (int) $data['capacity'] : null,
            location: $data['location'] ?? null,
            description: $data['description'] ?? null,
            image_path: $data['image_path'] ?? null,
            equipment_list: $data['equipment_list'] ?? null,
            safety_requirements: $data['safety_requirements'] ?? null,
            rules: $data['rules'] ?? null,
            hourly_rate: isset($data['hourly_rate']) ? (float) $data['hourly_rate'] : null,
            opens_at: isset($data['opens_at']) ? (int) $data['opens_at'] : null,
            closes_at: isset($data['closes_at']) ? (int) $data['closes_at'] : null,
            operating_days: $data['operating_days'] ?? null,
            slots_per_hour: isset($data['slots_per_hour']) ? (int) $data['slots_per_hour'] : null,
            bookings_enabled: $data['bookings_enabled'] ?? true,
            is_available: $data['is_available'] ?? true,
            available_from: isset($data['available_from']) ? new \DateTime($data['available_from']) : null,
            available_until: isset($data['available_until']) ? new \DateTime($data['available_until']) : null,
            featured_media_id: isset($data['featured_media_id']) ? (int) $data['featured_media_id'] : null,
            gallery_media_ids: $data['gallery_media_ids'] ?? null,
        );
    }

    /**
     * Convert DTO to array for model creation
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'county' => $this->county,
            'capacity' => $this->capacity,
            'location' => $this->location,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'equipment_list' => $this->equipment_list,
            'safety_requirements' => $this->safety_requirements,
            'rules' => $this->rules,
            'hourly_rate' => $this->hourly_rate,
            'opens_at' => $this->opens_at,
            'closes_at' => $this->closes_at,
            'operating_days' => $this->operating_days,
            'slots_per_hour' => $this->slots_per_hour,
            'bookings_enabled' => $this->bookings_enabled,
            'is_available' => $this->is_available,
            'available_from' => $this->available_from?->format('H:i'),
            'available_until' => $this->available_until?->format('H:i'),
            'featured_media_id' => $this->featured_media_id,
            'gallery_media_ids' => $this->gallery_media_ids,
        ];
    }
}
