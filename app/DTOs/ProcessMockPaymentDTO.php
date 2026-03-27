<?php

namespace App\DTOs;

/**
 * Process Mock Payment DTO
 *
 * Data Transfer Object for processing mock payment operations.
 */
class ProcessMockPaymentDTO
{
    public function __construct(
        public string $transaction_id,
        public string $phone,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            transaction_id: $data['transaction_id'],
            phone: $data['phone'],
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transaction_id,
            'phone' => $this->phone,
        ];
    }
}
