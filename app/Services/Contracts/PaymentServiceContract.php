<?php

namespace App\Services\Contracts;

use App\Models\Payment;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * PaymentServiceContract
 *
 * Defines contract for payment processing including creation,
 * verification, reconciliation, and payment tracking.
 */
interface PaymentServiceContract
{
    /**
     * Get payment form metadata
     *
     * @return array Metadata for payment forms
     */
    public function getPaymentFormMetadata(): array;

    /**
     * Check payment status by transaction ID
     *
     * @param string $transactionId Transaction ID
     * @return array Status data
     *
     * @throws \App\Exceptions\PaymentException
     */
    public function checkPaymentStatus(string $transactionId): array;

    /**
     * Verify payment with gateway
     *
     * @param Authenticatable $user The user
     * @param string $transactionId Transaction ID
     * @return array Verification result
     *
     * @throws \App\Exceptions\PaymentException
     */
    public function verifyPayment(Authenticatable $user, string $transactionId): array;

    /**
     * Process payment through gateway
     *
     * @param Authenticatable $user The user
     * @param array $data Payment data
     * @return array Processing result
     *
     * @throws \App\Exceptions\PaymentException
     */
    public function processPayment(Authenticatable $user, array $data): array;

    /**
     * Get payment history for user
     *
     * @param Authenticatable $user The user
     * @param int $perPage Results per page
     * @return LengthAwarePaginator History data with pagination
     */
    public function getPaymentHistory(Authenticatable $user, int $perPage = 15): LengthAwarePaginator;

    /**
     * List payments with filtering
     *
     * @param array $filters Filters
     * @param int $perPage Results per page
     * @return LengthAwarePaginator
     */
    public function listPayments(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Handle payment webhook
     *
     * @param array $data Webhook payload
     * @return array Result with event_id
     *
     * @throws \App\Exceptions\PaymentException
     */
    public function handleWebhook(array $data): array;

    /**
     * Refund a payment
     *
     * @param Authenticatable $user Admin user
     * @param array $data Refund data (payment_id, reason, etc.)
     * @return array Refund result
     *
     * @throws \App\Exceptions\PaymentException
     */
    public function refundPayment(Authenticatable $user, array $data): array;
}

