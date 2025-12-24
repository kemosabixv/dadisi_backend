<?php

namespace App\Services\PaymentGateway;

/**
 * Gateway Manager
 * 
 * THE SINGLE FORK POINT for payment gateway selection.
 * Reads config('payment.gateway') to determine which gateway to use.
 * 
 * Usage:
 *   $manager = new GatewayManager();
 *   $result = $manager->initiatePayment($paymentData);
 */
class GatewayManager
{
    protected PaymentGatewayInterface $gateway;

    public function __construct(?PaymentGatewayInterface $gateway = null)
    {
        // If a gateway instance is provided, use it (useful for testing)
        if ($gateway) {
            $this->gateway = $gateway;
            return;
        }

        // Otherwise, instantiate based on config
        $driver = config('payment.gateway', 'mock');
        
        switch ($driver) {
            case 'pesapal':
                $this->gateway = new PesapalGateway();
                break;
            case 'mock':
            default:
                $this->gateway = new MockGatewayAdapter();
                break;
        }
    }

    /**
     * Get the active gateway name.
     */
    public static function getActiveGateway(): string
    {
        return config('payment.gateway', 'mock');
    }

    /**
     * Check if mock gateway is active.
     */
    public static function isMockGateway(): bool
    {
        return self::getActiveGateway() === 'mock';
    }

    /**
     * Initiate a payment - creates a payment session and returns redirect URL.
     * This is the main entry point for starting a new payment.
     * 
     * @param array $paymentData Payment details including order_id, amount, currency, user_id, etc.
     * @param \Illuminate\Database\Eloquent\Model|null $model The model associated with this payment (Subscription, Donation, etc.)
     * @return array Should contain: success, transaction_id, redirect_url, order_tracking_id
     */
    public function initiatePayment(array $paymentData, ?\Illuminate\Database\Eloquent\Model $model = null): array
    {
        $result = $this->gateway->initiatePayment($paymentData);
        
        if ($result['success']) {
            \App\Models\AuditLog::log('payment.initiated', $model, null, [
                'amount' => $paymentData['amount'] ?? 0,
                'currency' => $paymentData['currency'] ?? 'KES',
                'order_id' => $paymentData['order_id'] ?? null,
                'transaction_id' => $result['transaction_id'] ?? null,
                'gateway' => self::getActiveGateway()
            ], 'Payment initiated via GatewayManager');
        }

        return $result;
    }

    /**
     * Charge/process a payment.
     * 
     * @param string $identifier Payment identifier (phone number, transaction ID, etc.)
     * @param int $amount Amount in smallest currency unit
     * @param array $metadata Additional payment context
     * @return array Should contain: success, status, error_message (if failed)
     */
    public function charge(string $identifier, int $amount, array $metadata = []): array
    {
        return $this->gateway->charge($identifier, $amount, $metadata);
    }

    /**
     * Query the status of an existing payment.
     * 
     * @param string $transactionId The transaction ID to query
     * @return array Payment status information
     */
    public function queryStatus(string $transactionId): array
    {
        return $this->gateway->queryStatus($transactionId);
    }

    /**
     * Get the underlying gateway instance (for advanced usage).
     */
    public function getGateway(): PaymentGatewayInterface
    {
        return $this->gateway;
    }
}
