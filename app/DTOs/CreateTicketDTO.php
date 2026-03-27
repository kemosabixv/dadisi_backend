<?php

namespace App\DTOs;

/**
 * Create Ticket DTO
 *
 * Data Transfer Object for event ticket creation operations.
 */
class CreateTicketDTO
{
    public function __construct(
        public int $event_id,
        public string $name,
        public int $quantity,
        public ?string $description = null,
        public ?float $price = null,
        public string $currency = 'KES',
        public ?int $order_limit = null,
        public bool $is_active = true,
        public ?\DateTime $available_until = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            event_id: (int) $data['event_id'],
            name: $data['name'],
            quantity: (int) $data['quantity'],
            description: $data['description'] ?? null,
            price: isset($data['price']) ? (float) $data['price'] : null,
            currency: $data['currency'] ?? 'KES',
            order_limit: isset($data['order_limit']) ? (int) $data['order_limit'] : null,
            is_active: $data['is_active'] ?? true,
            available_until: isset($data['available_until']) ? new \DateTime($data['available_until']) : null,
        );
    }

    /**
     * Convert DTO to array for model creation
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->event_id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'order_limit' => $this->order_limit,
            'is_active' => $this->is_active,
            'available_until' => $this->available_until?->format('Y-m-d H:i:s'),
        ];
    }
}
