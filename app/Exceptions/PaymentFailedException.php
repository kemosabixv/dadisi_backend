<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Exception for payment-related failures
 */
class PaymentFailedException extends Exception
{
    /**
     * @var string Payment ID if available
     */
    protected $paymentId;

    /**
     * @var array Additional context
     */
    protected $context = [];

    /**
     * Create a new PaymentFailedException instance
     *
     * @param string $message Error message
     * @param string|null $paymentId Payment ID
     * @param array $context Additional context
     * @param int $code Exception code
     */
    public function __construct(
        string $message = 'Payment processing failed',
        ?string $paymentId = null,
        array $context = [],
        int $code = 0
    ) {
        parent::__construct($message, $code);
        $this->paymentId = $paymentId;
        $this->context = $context;
    }

    /**
     * Get the payment ID
     */
    public function getPaymentId(): ?string
    {
        return $this->paymentId;
    }

    /**
     * Get context information
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Render the exception into an HTTP response
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => 'PAYMENT_FAILED',
            'payment_id' => $this->paymentId,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
