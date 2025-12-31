<?php

namespace App\DTOs;

/**
 * EventOrderDTO
 *
 * Data transfer object for event ticket orders.
 */
class EventOrderDTO
{
    public function __construct(
        public int $event_id,
        public int $quantity,
        public array $purchaser_data,
        public ?string $promo_code = null,
        public ?int $user_id = null,
    ) {}

    /**
     * Create from request data
     */
    public static function fromRequest(array $data, ?int $userId = null): self
    {
        return new self(
            event_id: (int) $data['event_id'],
            quantity: (int) $data['quantity'],
            purchaser_data: [
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
            ],
            promo_code: $data['promo_code'] ?? null,
            user_id: $userId,
        );
    }
}
