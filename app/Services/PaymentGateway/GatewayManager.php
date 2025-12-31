<?php

namespace App\Services\PaymentGateway;

use App\DTOs\Payments\PaymentRequestDTO;
use App\DTOs\Payments\PaymentStatusDTO;
use App\DTOs\Payments\TransactionResultDTO;
use Illuminate\Database\Eloquent\Model;

/**
 * GatewayManager
 *
 * THE SINGLE FORK POINT for payment gateway selection.
 * This class manages the active payment gateway and delegates
 * all payment operations to the appropriate implementation.
 *
 * Gateway selection is based on the `payment.gateway` config value.
 */
class GatewayManager
{
    /**
     * The active payment gateway implementation.
     */
    protected PaymentGatewayInterface $gateway;

    /**
     * Create a new GatewayManager instance.
     *
     * @param \App\Services\Contracts\SystemSettingServiceContract|null $settingService
     * @param PaymentGatewayInterface|null $gateway Optional gateway injection for testing
     */
    public function __construct(
        ?\App\Services\Contracts\SystemSettingServiceContract $settingService = null,
        ?PaymentGatewayInterface $gateway = null
    ) {
        if ($gateway) {
            $this->gateway = $gateway;
            return;
        }

        // 1. Determine driver from config or database
        $driver = $settingService ? $settingService->get('payment.gateway') : null;
        if (!$driver) {
            $driver = config('payment.gateway', 'mock');
        }
        
        switch ($driver) {
            case 'pesapal':
                // 2. Fetch specific Pesapal settings from database
                $pesapalConfig = [];
                if ($settingService) {
                    $dbSettings = $settingService->getSettings('pesapal');
                    $pesapalConfig = [
                        'consumer_key' => $dbSettings->get('pesapal.consumer_key'),
                        'consumer_secret' => $dbSettings->get('pesapal.consumer_secret'),
                        'environment' => $dbSettings->get('pesapal.environment'),
                        'callback_url' => $dbSettings->get('pesapal.callback_url'),
                        'ipn_url' => $dbSettings->get('pesapal.webhook_url'), // Map to ipn_url
                    ];
                    // Filter out nulls to allow fallback in PesapalGateway constructor
                    $pesapalConfig = array_filter($pesapalConfig);
                }

                $this->gateway = new PesapalGateway($pesapalConfig);
                break;
            case 'mock':
            default:
                $this->gateway = new MockGatewayAdapter();
                break;
        }
    }

    /**
     * Get the currently configured gateway driver name.
     *
     * @return string The gateway driver name ('pesapal', 'mock', etc.)
     */
    public static function getActiveGateway(): string
    {
        return config('payment.gateway', 'mock');
    }

    /**
     * Initiate a payment through the active gateway.
     *
     * Creates an audit log on successful initiation.
     *
     * @param PaymentRequestDTO $request The payment request data
     * @param Model|null $model Optional model to associate with audit log
     * @return TransactionResultDTO Contains success status, transaction ID, and redirect URL
     */
    public function initiatePayment(PaymentRequestDTO $request, ?Model $model = null): TransactionResultDTO
    {
        $result = $this->gateway->initiatePayment($request);
        
        if ($result->success) {
            \App\Models\AuditLog::log('payment.initiated', $model, null, [
                'amount' => $request->amount,
                'currency' => $request->currency,
                'reference' => $request->reference,
                'transaction_id' => $result->transactionId,
                'gateway' => self::getActiveGateway()
            ], 'Payment initiated via GatewayManager');
        }

        return $result;
    }

    /**
     * Process a direct charge through the active gateway.
     *
     * @param string $identifier The payment method identifier
     * @param int $amount The amount to charge
     * @param array $metadata Additional transaction metadata
     * @return TransactionResultDTO Contains success status and transaction details
     */
    public function charge(string $identifier, int $amount, array $metadata = []): TransactionResultDTO
    {
        return $this->gateway->charge($identifier, $amount, $metadata);
    }

    /**
     * Query payment status from the active gateway.
     *
     * @param string $transactionId The gateway transaction ID
     * @return PaymentStatusDTO Contains current payment status
     */
    public function queryStatus(string $transactionId): PaymentStatusDTO
    {
        return $this->gateway->queryStatus($transactionId);
    }

    /**
     * Refund a payment through the active gateway.
     *
     * @param string $transactionId The gateway transaction ID to refund
     * @param float $amount The amount to refund
     * @param string $reason The reason for the refund
     * @return TransactionResultDTO Contains success status and refund transaction details
     */
    public function refund(string $transactionId, float $amount, string $reason = ''): TransactionResultDTO
    {
        return $this->gateway->refund($transactionId, $amount, $reason);
    }

    /**
     * Get the underlying gateway implementation.
     *
     * @return PaymentGatewayInterface The active gateway instance
     */
    public function getGateway(): PaymentGatewayInterface
    {
        return $this->gateway;
    }
}
