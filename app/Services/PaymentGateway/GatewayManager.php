<?php

namespace App\Services\PaymentGateway;

class GatewayManager
{
    protected $gateway;

    public function __construct(?PaymentGatewayInterface $gateway = null)
    {
        // If a gateway instance is provided, use it. Otherwise instantiate from config.
        if ($gateway) {
            $this->gateway = $gateway;
            return;
        }

        $driver = config('payment.gateway', 'mock');
        switch ($driver) {
            case 'pesapal':
                $this->gateway = new PesapalGateway();
                break;
            case 'mock':
            default:
                // Use adapter that implements PaymentGatewayInterface and delegates to MockPaymentService
                $this->gateway = new MockGatewayAdapter();
                break;
        }
    }

    public function charge(string $identifier, int $amount, array $metadata = []): array
    {
        return $this->gateway->charge($identifier, $amount, $metadata);
    }
}
