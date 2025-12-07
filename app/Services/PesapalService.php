<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PesapalService
{
    protected const API_BASE_SANDBOX = 'https://cybqa.pesapal.com/pesapalv3/api';
    protected const API_BASE_LIVE = 'https://pay.pesapal.com/v3/api';

    protected $environment;
    protected $consumerKey;
    protected $consumerSecret;
    protected $callbackUrl;
    protected $webhookUrl;

    public function __construct()
    {
        $this->environment = config('services.pesapal.environment');
        $this->consumerKey = config('services.pesapal.consumer_key');
        $this->consumerSecret = config('services.pesapal.consumer_secret');
        $this->callbackUrl = config('services.pesapal.callback_url');
        $this->webhookUrl = config('services.pesapal.webhook_url');
    }

    /**
     * Get API base URL
     */
    protected function getApiBase(): string
    {
        return $this->environment === 'live'
            ? config('services.pesapal.live_url', self::API_BASE_LIVE)
            : config('services.pesapal.sandbox_url', self::API_BASE_SANDBOX);
    }

    /**
     * Get OAuth2 access token
     */
    protected function getAccessToken(): ?string
    {
        try {
            $response = Http::asForm()->post($this->getApiBase() . '/Auth/RequestToken', [
                'consumer_key' => $this->consumerKey,
                'consumer_secret' => $this->consumerSecret,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['token'] ?? null;
            }

            Log::error('Pesapal token request failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

        } catch (\Exception $e) {
            Log::error('Pesapal token request exception', [
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Generate unique order tracking ID
     */
    protected function generateTrackingId(): string
    {
        return uniqid('pspl_', true);
    }

    /**
     * Submit payment order request
     */
    public function submitOrder(
        Payment $payment,
        string $customerEmail,
        string $customerPhone,
        string $description = ''
    ): array {
        if ($this->environment === 'local' || app()->environment('local')) {
            return $this->mockSubmitOrder($payment, $customerEmail, $customerPhone, $description);
        }

        $token = $this->getAccessToken();

        if (!$token) {
            throw new \Exception('Failed to get Pesapal access token');
        }

        try {
            $response = Http::withToken($token)->post($this->getApiBase() . '/Transactions/SubmitOrderRequest', [
                'id' => $payment->id,
                'currency' => $payment->currency,
                'amount' => $payment->amount,
                'description' => $description ?: "Payment for {$payment->payable_type}",
                'callback_url' => $this->callbackUrl,
                'notification_id' => $payment->id,
                'billing_address' => [
                    'email_address' => $customerEmail,
                    'phone_number' => $customerPhone,
                    'country_code' => 'KE',
                ],
            ]);

            Log::info('Pesapal order submit response', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Update payment with external references
                $payment->update([
                    'external_reference' => $data['order_tracking_id'] ?? null,
                    'meta' => array_merge($payment->meta ?? [], [
                        'pesapal_redirect_url' => $data['redirect_url'] ?? null,
                        'pesapal_status' => $data['status'] ?? null,
                    ]),
                ]);

                return $data;
            }

            Log::error('Pesapal order submit failed', [
                'payment_id' => $payment->id,
                'response' => $response->json(),
            ]);

            throw new \Exception('Failed to submit order to Pesapal');

        } catch (\Exception $e) {
            Log::error('Pesapal order submit exception', [
                'payment_id' => $payment->id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Mock payment submission for local development
     */
    protected function mockSubmitOrder(
        Payment $payment,
        string $customerEmail,
        string $customerPhone,
        string $description
    ): array {
        Log::info('Mock Pesapal payment submission', [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
        ]);

        $mockRedirectUrl = url("/mock-payment/{$payment->id}");

        $payment->update([
            'meta' => array_merge($payment->meta ?? [], [
                'pesapal_redirect_url' => $mockRedirectUrl,
                'pesapal_status' => 'pending',
            ]),
        ]);

        return [
            'order_tracking_id' => $this->generateTrackingId(),
            'redirect_url' => $mockRedirectUrl,
            'status' => 'pending',
        ];
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        // Pesapal uses SHA-256 hash of payload + consumer secret
        $expectedSignature = hash('sha256', json_encode($payload) . $this->consumerSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process webhook notification
     */
    public function processWebhook(array $payload): array
    {
        Log::info('Processing Pesapal webhook', [
            'payload' => $payload,
        ]);

        // Find payment by order reference or external reference
        $payment = Payment::where('order_reference', $payload['OrderTrackingId'] ?? null)
            ->orWhere('external_reference', $payload['OrderTrackingId'] ?? null)
            ->first();

        if (!$payment) {
            Log::warning('Payment not found for webhook', [
                'order_tracking_id' => $payload['OrderTrackingId'] ?? null,
            ]);
            return ['status' => 'payment_not_found'];
        }

        // Update payment status
        $status = strtolower($payload['OrderNotificationType'] ?? 'failed');

        $updateData = [
            'status' => match($status) {
                'payment_received' => 'paid',
                'payment_completed' => 'paid',
                'cancelled' => 'cancelled',
                default => 'failed',
            },
        ];

        if ($status === 'payment_completed' || $status === 'payment_received') {
            $updateData['paid_at'] = now();
            $updateData['receipt_url'] = $payload['OrderMerchantReference'] ?? null;
        }

        $payment->update([
            'meta' => array_merge($payment->meta ?? [], [
                'webhook_processed_at' => now()->toISOString(),
                'webhook_payload' => $payload,
            ]),
        ] + $updateData);

        Log::info('Payment updated from webhook', [
            'payment_id' => $payment->id,
            'new_status' => $updateData['status'],
        ]);

        // TODO: Trigger business logic here (e.g., activate subscription)

        return [
            'status' => 'processed',
            'payment_id' => $payment->id,
            'new_status' => $updateData['status'],
        ];
    }

    /**
     * Process callback (redirect back to frontend)
     */
    public function processCallback(array $params): array
    {
        Log::info('Processing Pesapal callback', [
            'params' => $params,
        ]);

        $orderTrackingId = $params['OrderTrackingId'] ?? null;
        $orderMerchantReference = $params['OrderMerchantReference'] ?? null;

        $payment = Payment::where('external_reference', $orderTrackingId)
            ->orWhere('order_reference', $orderTrackingId)
            ->first();

        if (!$payment) {
            Log::warning('Payment not found for callback', [
                'order_tracking_id' => $orderTrackingId,
            ]);
            return ['status' => 'payment_not_found'];
        }

        // Update meta with callback data
        $payment->update([
            'meta' => array_merge($payment->meta ?? [], [
                'callback_processed_at' => now()->toISOString(),
                'callback_params' => $params,
            ]),
        ]);

        return [
            'status' => 'processed',
            'payment' => $payment,
            'new_status' => $payment->status,
        ];
    }
}
