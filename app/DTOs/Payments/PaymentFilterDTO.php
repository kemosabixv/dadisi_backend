<?php

namespace App\DTOs\Payments;

/**
 * PaymentFilterDTO
 *
 * Data transfer object for filtering payments.
 */
class PaymentFilterDTO
{
    public function __construct(
        public ?string $status = null,
        public ?int $payer_id = null,
        public ?string $county = null,
        public ?string $payment_method = null,
        public ?string $date_from = null,
        public ?string $date_to = null,
        public ?float $amount_from = null,
        public ?float $amount_to = null,
        public ?string $payable_type = null,
        public ?int $payable_id = null,
    ) {}

    /**
     * Create DTO from array
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] ?? null,
            payer_id: isset($data['payer_id']) ? (int) $data['payer_id'] : null,
            county: $data['county'] ?? null,
            payment_method: $data['payment_method'] ?? null,
            date_from: $data['date_from'] ?? null,
            date_to: $data['date_to'] ?? null,
            amount_from: isset($data['amount_from']) ? (float) $data['amount_from'] : null,
            amount_to: isset($data['amount_to']) ? (float) $data['amount_to'] : null,
            payable_type: $data['payable_type'] ?? null,
            payable_id: isset($data['payable_id']) ? (int) $data['payable_id'] : null,
        );
    }

    /**
     * Convert DTO to array for database query
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status,
            'payer_id' => $this->payer_id,
            'county' => $this->county,
            'payment_method' => $this->payment_method,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'amount_from' => $this->amount_from,
            'amount_to' => $this->amount_to,
            'payable_type' => $this->payable_type,
            'payable_id' => $this->payable_id,
        ], fn($value) => $value !== null);
    }
}
