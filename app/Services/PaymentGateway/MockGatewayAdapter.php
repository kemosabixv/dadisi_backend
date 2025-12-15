<?php

namespace App\Services\PaymentGateway;

use App\Services\MockPaymentService;

class MockGatewayAdapter implements PaymentGatewayInterface
{
    /**
     * Delegate to the project's MockPaymentService::processPayment
     */
    public function charge(string $identifier, int $amount, array $metadata = []): array
    {
        // MockPaymentService expects phone/identifier and optional context
        $res = MockPaymentService::processPayment($identifier, array_merge($metadata, ['amount' => $amount]));

        // Normalize response to gateway interface
        return [
            'success' => $res['success'] ?? false,
            'status' => $res['status'] ?? ($res['success'] ? 'success' : 'failed'),
            'error_message' => $res['error_message'] ?? null,
            'raw' => $res,
        ];
    }
}
