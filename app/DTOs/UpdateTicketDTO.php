<?php

namespace App\DTOs;

/**
 * Update Ticket DTO
 *
 * Data Transfer Object for event ticket update operations.
 */
class UpdateTicketDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?float $price = null,
        public ?int $quantity = null,
        public ?int $order_limit = null,
        public ?bool $is_active = null,
        public ?\DateTime $available_until = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            price: isset($data['price']) ? (float) $data['price'] : null,
            quantity: isset($data['quantity']) ? (int) $data['quantity'] : null,
            order_limit: isset($data['order_limit']) ? (int) $data['order_limit'] : null,
            is_active: isset($data['is_active']) ? (bool) $data['is_active'] : null,
            available_until: isset($data['available_until']) ? new \DateTime($data['available_until']) : null,
        );
    }

    /**
     * Convert DTO to array, filtering out null values
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'order_limit' => $this->order_limit,
            'is_active' => $this->is_active,
            'available_until' => $this->available_until?->format('Y-m-d H:i:s'),
        ], fn ($value) => $value !== null);
    }
}
