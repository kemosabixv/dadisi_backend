<?php

namespace App\DTOs\Payments;

/**
 * PaymentStatusDTO
 *
 * Data transfer object for payment status checks.
 * Represents the current state of a payment transaction.
 *
 * @property string $transactionId The gateway transaction identifier
 * @property string $merchantReference The merchant's reference
 * @property string $status Current payment status (PENDING, COMPLETED, FAILED, etc.)
 * @property float $amount The payment amount
 * @property string $currency Currency code (default: KES)
 * @property string|null $paymentMethod The payment method used
 * @property string|null $paidAt Timestamp when payment was completed
 * @property array $rawDetails Raw gateway response details
 */
class PaymentStatusDTO
{
    /**
     * Create a new PaymentStatusDTO instance.
     *
     * @param string $transactionId Gateway transaction ID
     * @param string $merchantReference Merchant reference
     * @param string $status Current status
     * @param float $amount Payment amount
     * @param string $currency Currency code
     * @param string|null $paymentMethod Method used
     * @param string|null $paidAt Payment completion time
     * @param array $rawDetails Raw gateway response
     */
    public function __construct(
        public string $transactionId,
        public string $merchantReference,
        public string $status,
        public float $amount,
        public string $currency = 'KES',
        public ?string $paymentMethod = null,
        public ?string $paidAt = null,
        public array $rawDetails = [],
    ) {}

    /**
     * Check if the payment is considered successful.
     *
     * @return bool True if payment status indicates success
     */
    public function isPaid(): bool
    {
        return in_array(strtoupper($this->status), ['COMPLETED', 'PAID', 'SUCCESS']);
    }

    /**
     * Check if the payment has failed.
     *
     * @return bool True if payment status indicates failure
     */
    public function isFailed(): bool
    {
        return in_array(strtoupper($this->status), ['FAILED', 'CANCELLED', 'REJECTED', 'INVALID']);
    }

    /**
     * Check if the payment is still pending.
     *
     * @return bool True if payment is pending
     */
    public function isPending(): bool
    {
        return strtoupper($this->status) === 'PENDING';
    }
}
