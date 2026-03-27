<?php

namespace App\DTOs;

/**
 * Create Promo Code DTO
 *
 * Data Transfer Object for promo code creation operations.
 */
class CreatePromoCodeDTO
{
    public function __construct(
        public int $event_id,
        public string $code,
        public string $discount_type,
        public float $discount_value,
        public ?int $ticket_id = null,
        public ?int $usage_limit = null,
        public bool $is_active = true,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            event_id: (int) $data['event_id'],
            code: $data['code'],
            discount_type: $data['discount_type'],
            discount_value: (float) $data['discount_value'],
            ticket_id: isset($data['ticket_id']) ? (int) $data['ticket_id'] : null,
            usage_limit: isset($data['usage_limit']) ? (int) $data['usage_limit'] : null,
            is_active: $data['is_active'] ?? true,
        );
    }

    /**
     * Convert DTO to array for model creation
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->event_id,
            'code' => $this->code,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'ticket_id' => $this->ticket_id,
            'usage_limit' => $this->usage_limit,
            'is_active' => $this->is_active,
        ];
    }
}
