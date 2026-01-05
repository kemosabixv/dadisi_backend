<?php

namespace App\Services\PaymentGateway;

use App\DTOs\Payments\PaymentRequestDTO;
use App\DTOs\Payments\PaymentStatusDTO;
use App\DTOs\Payments\TransactionResultDTO;
use App\Services\Payments\MockPaymentService;

/**
 * Mock Gateway Adapter
 * 
 * Adapts MockPaymentService to the PaymentGatewayInterface.
 * Used in local/development/staging environments.
 */
class MockGatewayAdapter implements PaymentGatewayInterface
{
    /**
     * Initiate a mock payment session.
     */
    public function initiatePayment(PaymentRequestDTO $request): TransactionResultDTO
    {
        $metadata = $request->metadata;
        
        $data = [
            'amount' => $request->amount,
            'currency' => $request->currency,
            // If it's a subscription, use the numerical ID as order_id for controller lookup compat
            'order_id' => ($request->payable_type === 'App\\Models\\PlanSubscription') 
                ? $request->payable_id 
                : ($request->reference ?? $request->metadata['payment_id'] ?? null),
            'user_id' => $request->metadata['user_id'] ?? null,
            'plan_id' => $request->metadata['plan_id'] ?? null,
            'billing_period' => $request->metadata['billing_period'] ?? 'month',
            'description' => $request->description,
        ];

        $res = MockPaymentService::initiatePayment($data);

        return TransactionResultDTO::success(
            transactionId: $res['transaction_id'] ?? '',
            merchantReference: $request->reference ?? '',
            redirectUrl: $res['redirect_url'] ?? null,
            status: 'PENDING',
            message: 'Mock payment initiated',
            rawResponse: $res
        );
    }

    /**
     * Process a mock payment (charge).
     */
    public function charge(string $identifier, int $amount, array $metadata = []): TransactionResultDTO
    {
        $data = array_merge($metadata, ['amount' => $amount / 100]); // Mock service expects decimal
        $res = MockPaymentService::processPayment($identifier, $data);

        if (($res['status'] ?? '') === 'failed') {
            return TransactionResultDTO::failure($res['error_message'] ?? 'Mock payment failed', $res);
        }

        return TransactionResultDTO::success(
            transactionId: $res['transaction_id'] ?? '',
            merchantReference: $identifier,
            status: strtoupper($res['status'] ?? 'COMPLETED'),
            message: $res['message'] ?? 'Mock payment processed',
            rawResponse: $res
        );
    }

    /**
     * Query status of a mock payment.
     */
    public function queryStatus(string $transactionId): PaymentStatusDTO
    {
        $res = MockPaymentService::queryPaymentStatus($transactionId);

        return new PaymentStatusDTO(
            transactionId: $transactionId,
            merchantReference: $transactionId,
            status: strtoupper($res['status'] ?? 'UNKNOWN'),
            amount: (float) ($res['amount'] ?? 0),
            currency: $res['currency'] ?? 'KES',
            rawDetails: $res
        );
    }

    /**
     * Refund a mock payment.
     */
    public function refund(string $transactionId, float $amount, string $reason = ''): TransactionResultDTO
    {
        return TransactionResultDTO::success(
            transactionId: 'REF_' . $transactionId,
            merchantReference: $transactionId,
            status: 'REFUNDED',
            message: 'Mock refund processed',
            rawResponse: ['transaction_id' => $transactionId, 'amount' => $amount, 'reason' => $reason]
        );
    }
}
