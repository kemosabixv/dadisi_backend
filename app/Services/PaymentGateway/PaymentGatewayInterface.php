<?php

namespace App\Services\PaymentGateway;

use App\DTOs\Payments\PaymentRequestDTO;
use App\DTOs\Payments\TransactionResultDTO;
use App\DTOs\Payments\PaymentStatusDTO;

/**
 * PaymentGatewayInterface
 *
 * Defines the contract for all payment gateway implementations.
 * Each gateway (Pesapal, Mock, etc.) must implement these methods
 * to ensure consistent payment processing across the application.
 */
interface PaymentGatewayInterface
{
    /**
     * Initiate a payment session and return a redirect URL.
     *
     * Creates a new payment order with the gateway and returns
     * the redirect URL where the user should complete payment.
     *
     * @param PaymentRequestDTO $request The payment request data
     * @return TransactionResultDTO Contains success status, transaction ID, and redirect URL
     *
     * @throws \App\Exceptions\PaymentException When gateway initialization fails
     */
    public function initiatePayment(PaymentRequestDTO $request): TransactionResultDTO;

    /**
     * Charge/process a payment directly.
     *
     * Used for direct charges without user redirect (e.g., saved payment methods).
     *
     * @param string $identifier The payment method identifier (token, phone number, etc.)
     * @param int $amount The amount to charge in smallest currency unit
     * @param array $metadata Additional metadata for the transaction
     * @return TransactionResultDTO Contains success status and transaction details
     *
     * @throws \App\Exceptions\PaymentException When charge fails
     */
    public function charge(string $identifier, int $amount, array $metadata = []): TransactionResultDTO;

    /**
     * Query the status of an existing payment.
     *
     * Fetches the current status of a transaction from the gateway.
     *
     * @param string $transactionId The gateway transaction ID
     * @return PaymentStatusDTO Contains current payment status and details
     *
     * @throws \App\Exceptions\PaymentException When status query fails
     */
    public function queryStatus(string $transactionId): PaymentStatusDTO;

    /**
     * Refund a previously processed payment.
     *
     * @param string $transactionId The gateway transaction ID to refund
     * @param float $amount The amount to refund
     * @param string $reason The reason for the refund
     * @return TransactionResultDTO Contains success status and refund transaction details
     *
     * @throws \App\Exceptions\PaymentException When refund fails
     */
    public function refund(string $transactionId, float $amount, string $reason = ''): TransactionResultDTO;
}
