<?php

namespace App\DTOs;

/**
 * Initiate Subscription Payment DTO
 *
 * Data Transfer Object for initiating subscription payment operations.
 */
class InitiateSubscriptionPaymentDTO
{
    public function __construct(
        public int $plan_id,
        public ?string $billing_period = null,
        public ?string $phone = null,
        public ?string $payment_method = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            plan_id: (int) $data['plan_id'],
            billing_period: $data['billing_period'] ?? null,
            phone: $data['phone'] ?? null,
            payment_method: $data['payment_method'] ?? null,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'plan_id' => $this->plan_id,
            'billing_period' => $this->billing_period,
            'phone' => $this->phone,
            'payment_method' => $this->payment_method,
        ];
    }
}
