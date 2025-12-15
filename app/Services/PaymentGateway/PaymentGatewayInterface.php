<?php

namespace App\Services\PaymentGateway;

interface PaymentGatewayInterface
{
    /**
     * Charge a payment identifier for a given amount and metadata.
     * Should return an array with at least ['success' => bool, 'status' => string]
     *
     * @param string $identifier
     * @param int $amount
     * @param array $metadata
     * @return array
     */
    public function charge(string $identifier, int $amount, array $metadata = []): array;
}
