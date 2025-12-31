<?php

namespace App\DTOs;

/**
 * SubscriptionRequestDTO
 *
 * Data transfer object for subscription initiation and renewal requests.
 */
class SubscriptionRequestDTO
{
    public function __construct(
        public int $plan_id,
        public string $billing_period = 'month',
        public ?string $phone = null,
        public ?string $payment_method = 'pesapal',
        public ?string $coupon_code = null,
        public array $metadata = [],
    ) {}

    /**
     * Create from array/request
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            plan_id: (int) $data['plan_id'],
            billing_period: $data['billing_period'] ?? 'month',
            phone: $data['phone'] ?? null,
            payment_method: $data['payment_method'] ?? 'pesapal',
            coupon_code: $data['coupon_code'] ?? null,
            metadata: $data['metadata'] ?? [],
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
            'coupon_code' => $this->coupon_code,
            'metadata' => $this->metadata,
        ];
    }
}
