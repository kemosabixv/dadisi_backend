<?php

namespace App\DTOs\Payments;

/**
 * TransactionResultDTO
 *
 * Data transfer object for payment processing results.
 * Normalizes responses from different payment gateways into a consistent structure.
 *
 * @property bool $success Whether the operation succeeded
 * @property string|null $transactionId The gateway's transaction identifier
 * @property string|null $merchantReference The merchant's reference for the transaction
 * @property string|null $redirectUrl URL to redirect user for payment completion
 * @property string|null $status Transaction status (PENDING, COMPLETED, FAILED, etc.)
 * @property string|null $message Human-readable status message
 * @property array $rawResponse The original gateway response for debugging
 */
class TransactionResultDTO
{
    /**
     * Create a new TransactionResultDTO instance.
     *
     * @param bool $success Whether the operation succeeded
     * @param string|null $transactionId Gateway transaction ID
     * @param string|null $merchantReference Merchant reference
     * @param string|null $redirectUrl Redirect URL for user
     * @param string|null $status Transaction status
     * @param string|null $message Status message
     * @param array $rawResponse Raw gateway response
     */
    public function __construct(
        public bool $success,
        public ?string $transactionId = null,
        public ?string $merchantReference = null,
        public ?string $redirectUrl = null,
        public ?string $status = 'PENDING',
        public ?string $message = null,
        public array $rawResponse = [],
    ) {}

    /**
     * Create a successful result.
     *
     * Factory method for creating successful transaction results.
     *
     * @param string $transactionId The gateway transaction ID
     * @param string $merchantReference The merchant reference
     * @param string|null $redirectUrl Optional redirect URL
     * @param string $status Transaction status (default: PENDING)
     * @param string|null $message Optional message
     * @param array $rawResponse Raw gateway response
     * @return self
     */
    public static function success(
        string $transactionId,
        string $merchantReference,
        ?string $redirectUrl = null,
        string $status = 'PENDING',
        ?string $message = null,
        array $rawResponse = []
    ): self {
        return new self(
            success: true,
            transactionId: $transactionId,
            merchantReference: $merchantReference,
            redirectUrl: $redirectUrl,
            status: $status,
            message: $message,
            rawResponse: $rawResponse
        );
    }

    /**
     * Create a failed result.
     *
     * Factory method for creating failed transaction results.
     *
     * @param string $message Error message describing the failure
     * @param array $rawResponse Raw gateway response for debugging
     * @return self
     */
    public static function failure(string $message, array $rawResponse = []): self
    {
        return new self(
            success: false,
            message: $message,
            rawResponse: $rawResponse
        );
    }
}
