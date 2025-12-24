<?php

namespace App\Services\PaymentGateway;

interface PaymentGatewayInterface
{
    /**
     * Initiate a payment - creates a payment session and returns redirect URL.
     * This is the first step before the user is redirected to pay.
     *
     * @param array $paymentData Payment details including order_id, amount, currency, user_id, etc.
     * @return array Should contain: success, transaction_id, redirect_url, order_tracking_id
     */
    public function initiatePayment(array $paymentData): array;

    /**
     * Charge/process a payment - processes the actual payment.
     * Used for immediate payment processing or status updates.
     *
     * @param string $identifier Payment identifier (phone number, transaction ID, etc.)
     * @param int $amount Amount in smallest currency unit
     * @param array $metadata Additional payment context
     * @return array Should contain: success, status, error_message (if failed)
     */
    public function charge(string $identifier, int $amount, array $metadata = []): array;

    /**
     * Query the status of an existing payment.
     *
     * @param string $transactionId The transaction ID to query
     * @return array Payment status information
     */
    public function queryStatus(string $transactionId): array;
}
