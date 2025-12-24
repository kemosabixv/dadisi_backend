<?php

namespace App\Services\PaymentGateway;

use App\Services\MockPaymentService;

/**
 * Mock Gateway Adapter
 * 
 * Adapts MockPaymentService to the PaymentGatewayInterface.
 * Used in local/development/staging environments.
 */
class MockGatewayAdapter implements PaymentGatewayInterface
{
    /**
     * Initiate a mock payment - creates a payment session and returns redirect URL.
     * 
     * @param array $paymentData Payment details
     * @return array Payment initiation response with redirect_url
     */
    public function initiatePayment(array $paymentData): array
    {
        return MockPaymentService::initiatePayment($paymentData);
    }

    /**
     * Process a mock payment (charge).
     * 
     * @param string $identifier Phone number or payment identifier
     * @param int $amount Amount in smallest currency unit
     * @param array $metadata Additional payment context
     * @return array Normalized response
     */
    public function charge(string $identifier, int $amount, array $metadata = []): array
    {
        $res = MockPaymentService::processPayment($identifier, array_merge($metadata, ['amount' => $amount]));

        // Normalize response to gateway interface
        return [
            'success' => $res['success'] ?? false,
            'status' => $res['status'] ?? ($res['success'] ? 'success' : 'failed'),
            'error_message' => $res['error_message'] ?? null,
            'raw' => $res,
        ];
    }

    /**
     * Query status of a mock payment.
     * 
     * @param string $transactionId Transaction ID to query
     * @return array Payment status
     */
    public function queryStatus(string $transactionId): array
    {
        return MockPaymentService::queryPaymentStatus($transactionId);
    }
}
