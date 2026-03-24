<?php

namespace App\DTOs;

/**
 * Cancel Subscription Payment DTO
 *
 * Data Transfer Object for subscription payment cancellation operations.
 */
class CancelSubscriptionPaymentDTO
{
    public function __construct(
        public int $subscriptionId,
        public ?string $reason = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            subscriptionId: (int) $data['subscription_id'],
            reason: $data['reason'] ?? null,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        $data = [
            'subscription_id' => $this->subscriptionId,
        ];

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;
        }

        return $data;
    }
}
