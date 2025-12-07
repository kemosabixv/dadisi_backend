<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Mock Payment Service
 *
 * Simulates Pesapal payment gateway for development and testing.
 * In production, this would integrate with real Pesapal API.
 */
class MockPaymentService
{
    private const SUCCESS_PHONE_PATTERNS = [
        '254701234567', // Success pattern
        '254702000000', // Success range
    ];

    private const FAILURE_PHONE_PATTERNS = [
        '254709999999', // Explicit failure
        '254708888888', // Network error
    ];

    private const PENDING_PHONE_PATTERNS = [
        '254707777777', // Pending/timeout
    ];

    /**
     * Initiate a mock payment
     *
     * @param array $paymentData Payment details
     * @return array Payment initiation response
     */
    public static function initiatePayment(array $paymentData): array
    {
        Log::info('Mock payment initiated', $paymentData);

        // Generate mock transaction ID
        $transactionId = 'MOCK_' . Str::random(12);
        $orderTrackingId = 'ORDER_' . Str::random(16);

        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'order_tracking_id' => $orderTrackingId,
            'redirect_url' => route('api.payments.mock.checkout', [
                'transaction_id' => $transactionId,
                'order_id' => $paymentData['order_id'] ?? null,
                'amount' => $paymentData['amount'] ?? 0,
            ]),
            'message' => 'Mock payment initialized successfully',
            'gateway' => 'mock_pesapal',
        ];
    }

    /**
     * Process a mock payment based on phone number pattern
     *
     * @param string $phone Phone number to simulate different outcomes
     * @param array $transactionData Transaction details
     * @return array Payment processing result
     */
    public static function processPayment(string $phone, array $transactionData): array
    {
        Log::info('Mock payment processing', [
            'phone' => $phone,
            'transaction_id' => $transactionData['transaction_id'] ?? null,
        ]);

        // Determine outcome based on phone pattern
        if (self::isSuccessPhone($phone)) {
            return self::createSuccessResponse($transactionData);
        } elseif (self::isFailurePhone($phone)) {
            return self::createFailureResponse($transactionData, 'Card declined');
        } elseif (self::isPendingPhone($phone)) {
            return self::createPendingResponse($transactionData);
        }

        // Default: random success (70% success rate for default numbers)
        return rand(1, 100) <= 70
            ? self::createSuccessResponse($transactionData)
            : self::createFailureResponse($transactionData, 'Generic payment failure');
    }

    /**
     * Query payment status (mock)
     *
     * @param string $transactionId Transaction ID
     * @return array Payment status
     */
    public static function queryPaymentStatus(string $transactionId): array
    {
        Log::info('Mock payment status queried', ['transaction_id' => $transactionId]);

        // Simulate status retrieval
        $statuses = ['completed', 'pending', 'failed'];
        $status = $statuses[rand(0, 2)];

        return [
            'transaction_id' => $transactionId,
            'status' => $status,
            'amount' => rand(1000, 50000) / 100,
            'currency' => 'KES',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Refund a payment (mock)
     *
     * @param string $transactionId Original transaction ID
     * @param float $amount Amount to refund
     * @return array Refund response
     */
    public static function refundPayment(string $transactionId, float $amount): array
    {
        Log::info('Mock refund initiated', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
        ]);

        return [
            'success' => true,
            'refund_id' => 'REFUND_' . Str::random(12),
            'original_transaction_id' => $transactionId,
            'refund_amount' => $amount,
            'status' => 'processing',
            'message' => 'Refund initiated successfully',
        ];
    }

    /**
     * Check if phone number should result in success
     */
    private static function isSuccessPhone(string $phone): bool
    {
        return in_array($phone, self::SUCCESS_PHONE_PATTERNS) ||
               preg_match('/^2547(0|1|2|3|4|5)/', $phone);
    }

    /**
     * Check if phone number should result in failure
     */
    private static function isFailurePhone(string $phone): bool
    {
        return in_array($phone, self::FAILURE_PHONE_PATTERNS);
    }

    /**
     * Check if phone number should result in pending
     */
    private static function isPendingPhone(string $phone): bool
    {
        return in_array($phone, self::PENDING_PHONE_PATTERNS);
    }

    /**
     * Create success response
     */
    private static function createSuccessResponse(array $transactionData): array
    {
        return [
            'success' => true,
            'status' => 'completed',
            'transaction_id' => $transactionData['transaction_id'] ?? 'MOCK_' . Str::random(12),
            'order_id' => $transactionData['order_id'] ?? null,
            'amount' => $transactionData['amount'] ?? 0,
            'currency' => 'KES',
            'message' => 'Payment completed successfully',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Create failure response
     */
    private static function createFailureResponse(array $transactionData, string $reason): array
    {
        return [
            'success' => false,
            'status' => 'failed',
            'transaction_id' => $transactionData['transaction_id'] ?? 'MOCK_' . Str::random(12),
            'order_id' => $transactionData['order_id'] ?? null,
            'error_code' => 'PAYMENT_FAILED',
            'error_message' => $reason,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Create pending response
     */
    private static function createPendingResponse(array $transactionData): array
    {
        return [
            'success' => false,
            'status' => 'pending',
            'transaction_id' => $transactionData['transaction_id'] ?? 'MOCK_' . Str::random(12),
            'order_id' => $transactionData['order_id'] ?? null,
            'message' => 'Payment is pending. Please try again later.',
            'retry_after' => 30,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
