<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Services\PaymentGateway\PesapalGateway;
use Illuminate\Support\Facades\Log;

/**
 * Payment Gateway Factory
 * 
 * Selects and creates the appropriate payment gateway based on system settings.
 */
class PaymentGatewayFactory
{
    /**
     * Get the active payment gateway.
     *
     * @return string The active gateway name: 'mock' or 'pesapal'
     */
    public static function getActiveGateway(): string
    {
        $setting = SystemSetting::where('key', 'payment.gateway')->first();
        return $setting ? $setting->value : 'mock';
    }

    /**
     * Initiate a payment using the configured gateway.
     *
     * @param array $paymentData Payment details
     * @return array Payment initiation response
     */
    public static function initiatePayment(array $paymentData): array
    {
        $gateway = self::getActiveGateway();
        
        Log::info('Payment initiated via gateway', [
            'gateway' => $gateway,
            'payment_data' => $paymentData,
        ]);

        if ($gateway === 'pesapal') {
            return self::initiatePesapalPayment($paymentData);
        }

        return MockPaymentService::initiatePayment($paymentData);
    }

    /**
     * Initiate payment via Pesapal gateway.
     *
     * @param array $paymentData Payment details
     * @return array Payment response
     */
    private static function initiatePesapalPayment(array $paymentData): array
    {
        try {
            $gateway = new PesapalGateway();
            
            $identifier = 'SUB-' . ($paymentData['order_id'] ?? uniqid());
            $amount = $paymentData['amount'] ?? 0;
            
            $result = $gateway->charge($identifier, $amount, [
                'description' => $paymentData['description'] ?? 'Subscription Payment',
                'phone' => $paymentData['phone'] ?? null,
                'email' => $paymentData['email'] ?? null,
                'first_name' => $paymentData['first_name'] ?? 'Customer',
                'last_name' => $paymentData['last_name'] ?? '',
            ]);

            return [
                'success' => $result['success'] ?? false,
                'transaction_id' => $result['transaction_id'] ?? null,
                'order_tracking_id' => $result['order_tracking_id'] ?? null,
                'redirect_url' => $result['redirect_url'] ?? null,
                'message' => $result['message'] ?? 'Payment initiated',
                'gateway' => 'pesapal',
            ];
        } catch (\Exception $e) {
            Log::error('Pesapal payment initiation failed', [
                'error' => $e->getMessage(),
                'payment_data' => $paymentData,
            ]);

            return [
                'success' => false,
                'message' => 'Payment gateway error: ' . $e->getMessage(),
                'gateway' => 'pesapal',
            ];
        }
    }

    /**
     * Query payment status from the appropriate gateway.
     *
     * @param string $transactionId Transaction ID
     * @param string $gateway Gateway used for the transaction
     * @return array Payment status
     */
    public static function queryPaymentStatus(string $transactionId, string $gateway = 'auto'): array
    {
        if ($gateway === 'auto') {
            $gateway = self::getActiveGateway();
        }

        if ($gateway === 'pesapal') {
            try {
                $gatewayService = new PesapalGateway();
                return $gatewayService->getTransactionStatus($transactionId);
            } catch (\Exception $e) {
                Log::error('Pesapal status query failed', [
                    'transaction_id' => $transactionId,
                    'error' => $e->getMessage(),
                ]);
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Failed to query payment status',
                ];
            }
        }

        return MockPaymentService::queryPaymentStatus($transactionId);
    }
}
