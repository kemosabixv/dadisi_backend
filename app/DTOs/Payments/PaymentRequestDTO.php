<?php

namespace App\DTOs\Payments;

/**
 * PaymentRequestDTO
 *
 * Data transfer object for payment creation/processing requests.
 * Contains all data needed to initiate a payment with the gateway.
 *
 * @property float $amount The payment amount
 * @property string $payment_method The payment method (pesapal, mpesa, card, etc.)
 * @property string $currency The currency code (default: KES)
 * @property string|null $description Payment description
 * @property string|null $reference Unique reference identifier
 * @property string|null $county County information for compliance
 * @property string|null $payable_type The payable model class
 * @property int|null $payable_id The payable model ID
 * @property array $metadata Additional metadata
 * @property string|null $email Payer's email address
 * @property string|null $phone Payer's phone number
 * @property string|null $first_name Payer's first name
 * @property string|null $last_name Payer's last name
 */
class PaymentRequestDTO
{
    /**
     * Create a new PaymentRequestDTO instance.
     *
     * @param float $amount The payment amount
     * @param string $payment_method The payment method identifier
     * @param string $currency The currency code
     * @param string|null $description Payment description
     * @param string|null $reference Unique reference
     * @param string|null $county County for compliance
     * @param string|null $payable_type Payable model type
     * @param int|null $payable_id Payable model ID
     * @param array $metadata Additional metadata
     * @param string|null $email Payer email
     * @param string|null $phone Payer phone
     * @param string|null $first_name Payer first name
     * @param string|null $last_name Payer last name
     */
    public function __construct(
        public float $amount,
        public string $payment_method,
        public string $currency = 'KES',
        public ?string $description = null,
        public ?string $reference = null,
        public ?string $county = null,
        public ?string $payable_type = null,
        public ?int $payable_id = null,
        public array $metadata = [],
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $first_name = null,
        public ?string $last_name = null,
    ) {}

    /**
     * Create DTO from array data.
     *
     * @param array $data Input data array
     * @return self New PaymentRequestDTO instance
     */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: (float) $data['amount'],
            payment_method: $data['payment_method'],
            currency: $data['currency'] ?? 'KES',
            description: $data['description'] ?? null,
            reference: $data['reference'] ?? null,
            county: $data['county'] ?? null,
            payable_type: $data['payable_type'] ?? null,
            payable_id: isset($data['payable_id']) ? (int) $data['payable_id'] : null,
            metadata: $data['metadata'] ?? [],
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            first_name: $data['first_name'] ?? null,
            last_name: $data['last_name'] ?? null,
        );
    }

    /**
     * Get billing address compiled for gateway.
     *
     * Returns an array formatted for payment gateway billing address requirements.
     *
     * @return array{email: ?string, phone: ?string, first_name: ?string, last_name: ?string, description: ?string}
     */
    public function getBillingAddress(): array
    {
        return [
            'email' => $this->email,
            'phone' => $this->phone,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'description' => $this->description,
        ];
    }
}
