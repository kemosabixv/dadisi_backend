<?php

namespace App\Services\PaymentGateway;

use App\DTOs\Payments\PaymentRequestDTO;
use App\DTOs\Payments\PaymentStatusDTO;
use App\DTOs\Payments\TransactionResultDTO;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class PesapalGateway implements PaymentGatewayInterface
{
    protected $consumerKey;
    protected $consumerSecret;
    protected $environment;
    protected $apiBase;
    protected $callbackUrl;
    protected $ipnUrl;
    protected $ipnNotificationType;

    public function __construct(array $config = [])
    {
        // Load config from provided array or from config/payment.php (or env fallbacks)
        $this->consumerKey = $config['consumer_key'] ?? config('payment.pesapal.consumer_key');
        $this->consumerSecret = $config['consumer_secret'] ?? config('payment.pesapal.consumer_secret');
        $this->environment = $config['environment'] ?? config('payment.pesapal.environment', 'sandbox');
        $this->apiBase = $config['api_base'] ?? config('payment.pesapal.api_base');
        $this->callbackUrl = $config['callback_url'] ?? config('payment.pesapal.callback_url', config('app.url') . '/payment/callback');
        $this->ipnUrl = $config['ipn_url'] ?? config('payment.pesapal.ipn_url', config('app.url') . '/webhooks/pesapal/ipn');
        $this->ipnNotificationType = $config['ipn_notification_type'] ?? config('payment.pesapal.ipn_notification_type', 'POST');
    }

    /**
     * Initiate a payment - creates a payment session with Pesapal and returns redirect URL.
     */
    public function initiatePayment(PaymentRequestDTO $request): TransactionResultDTO
    {
        // Generate a unique order identifier if not provided
        $orderId = $request->reference ?? ('ORDER_' . time() . '_' . \Illuminate\Support\Str::random(8));
        
        // Step 1: Get JWT Token
        $token = $this->getJwtToken();
        if (!$token) {
            return $this->normalizeError('authentication_failed', 'Failed to obtain JWT token');
        }

        // Step 2: Register/Get IPN ID
        $notificationId = $this->getNotificationId($token);
        if (!$notificationId) {
            return $this->normalizeError('ipn_failed', 'Failed to register IPN notification ID');
        }

        // Step 3: Submit Order
        $res = $this->submitOrderRequest(
            $token,
            $orderId,
            (int) ($request->amount * 100),
            array_merge($request->getBillingAddress(), $request->metadata), // Merge metadata for recurring params
            $notificationId
        );

        return $this->normalizeResponse($res, $orderId);
    }

    /**
     * Charge via Pesapal using API 3.0.
     */
    public function charge(string $identifier, int $amount, array $metadata = []): TransactionResultDTO
    {
        // Ensure gateway is configured for API calls
        if (empty($this->consumerKey) || empty($this->consumerSecret) || empty($this->apiBase)) {
            return $this->normalizeError('not_implemented', 'Pesapal gateway is not configured');
        }

        try {
            $token = $this->getJwtToken();
            if (!$token) {
                return $this->normalizeError('authentication_failed', 'Failed to obtain JWT token');
            }

            $notificationId = $this->getNotificationId($token);
            if (!$notificationId) {
                return $this->normalizeError('ipn_failed', 'Failed to register IPN');
            }

            $res = $this->submitOrderRequest($token, $identifier, $amount, $metadata, $notificationId);
            return $this->normalizeResponse($res, $identifier);

        } catch (\Exception $e) {
            \Log::error('Pesapal charge exception: ' . $e->getMessage());
            return $this->normalizeError('unknown_error', $e->getMessage());
        }
    }

    /**
     * Query the status of an existing payment.
     */
    public function queryStatus(string $transactionId): PaymentStatusDTO
    {
        $statusData = $this->getTransactionStatus($transactionId);
        
        return new PaymentStatusDTO(
            transactionId: $statusData['order_tracking_id'] ?? $transactionId,
            merchantReference: $statusData['merchant_reference'] ?? '',
            status: $statusData['status'] ?? 'UNKNOWN',
            amount: 0, // Not always returned in status query, though usually known from model
            rawDetails: $statusData
        );
    }

    /**
     * Token is valid for 5 minutes.
     */
    protected function getJwtToken(): ?string
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::asJson()->post(
                $this->apiBase . '/Auth/RequestToken',
                [
                    'consumer_key' => $this->consumerKey,
                    'consumer_secret' => $this->consumerSecret,
                ]
            );

            if ($response->successful()) {
                $contentType = strtolower($response->header('Content-Type') ?? '');
                if (str_contains($contentType, 'application/json')) {
                    $data = $response->json();
                    return $data['token'] ?? $data['access_token'] ?? $response->body();
                }

                return trim($response->body()) ?: null;
            }

            $error = $response->json()['error'] ?? [];
            \Log::error('Pesapal JWT token error', [
                'status' => $response->status(),
                'error' => $error['message'] ?? 'Unknown error',
            ]);
            return null;
        } catch (\Exception $e) {
            \Log::error('Pesapal JWT token exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get or register IPN ID.
     */
    protected function getNotificationId(string $token): ?string
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get($this->apiBase . '/URLSetup/GetIpnList');

            if ($response->successful()) {
                $ipnList = $response->json();
                
                if (is_array($ipnList)) {
                    foreach ($ipnList as $ipn) {
                        if ($ipn['url'] === $this->ipnUrl && ($ipn['ipn_status'] ?? 0) == 1) {
                            return $ipn['ipn_id'];
                        }
                    }
                }
            }

            return $this->registerIpnUrl($token);
        } catch (\Exception $e) {
            \Log::error('Pesapal IPN check error: ' . $e->getMessage());
            return null;
        }
    }

    protected function registerIpnUrl(string $token): ?string
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::asJson()->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->post(
                $this->apiBase . '/URLSetup/RegisterIPN',
                [
                    'url' => $this->ipnUrl,
                    'ipn_notification_type' => $this->ipnNotificationType,
                ]
            );

            if ($response->successful()) {
                return $response->json()['ipn_id'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('Pesapal IPN registration error: ' . $e->getMessage());
            return null;
        }
    }

    protected function submitOrderRequest(
        string $token,
        string $identifier,
        int $amount,
        array $metadata,
        string $notificationId
    ): array {
        try {
            $email = $metadata['email'] ?? '';
            $phone = $metadata['phone'] ?? '';

            if (empty($email) && empty($phone)) {
                return ['error' => 'Email or phone number is required'];
            }

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::asJson()->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->post(
                $this->apiBase . '/Transactions/SubmitOrderRequest',
                [
                    'id' => $identifier,
                    'currency' => 'KES',
                    'amount' => $amount / 100,
                    'description' => $metadata['description'] ?? 'Dadisi Community Labs Payment',
                    'callback_url' => $this->callbackUrl,
                    'notification_id' => $notificationId,
                    'account_number' => $metadata['account_number'] ?? null,
                    'subscription_details' => $metadata['subscription_details'] ?? null,
                    'billing_address' => [
                        'email_address' => $email,
                        'phone_number' => $phone,
                        'country_code' => 'KE',
                        'first_name' => $metadata['first_name'] ?? '',
                        'last_name' => $metadata['last_name'] ?? '',
                    ],
                ]
            );

            if ($response->successful()) {
                $contentType = strtolower($response->header('Content-Type') ?? '');
                $body = $response->body();

                if (str_contains($contentType, 'xml') || str_starts_with(trim($body), '<?xml')) {
                    $xml = simplexml_load_string($body);
                    return [
                        'order_tracking_id' => (string) ($xml->reference ?? $xml->order_tracking_id ?? ''),
                        'merchant_reference' => $identifier,
                        'redirect_url' => (string) ($xml->redirect_url ?? ''),
                        'status' => 'PENDING',
                    ];
                }

                return $response->json();
            }

            return ['error' => 'Failed to submit order'];
        } catch (\Exception $e) {
            \Log::error('Pesapal submit order error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function getTransactionStatus(string $orderTrackingId, string $merchantReference = ''): array
    {
        try {
            $token = $this->getJwtToken();
            if (!$token) return ['status' => 'UNKNOWN', 'error' => 'Auth failed'];

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get(
                $this->apiBase . '/Transactions/GetTransactionStatus',
                ['order_tracking_id' => $orderTrackingId]
            );

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'order_tracking_id' => $data['order_tracking_id'] ?? $orderTrackingId,
                    'merchant_reference' => $data['merchant_reference'] ?? $merchantReference,
                    'status' => $data['status'] ?? 'UNKNOWN',
                    'confirmation_code' => $data['confirmation_code'] ?? null,
                    'payment_method' => $data['payment_method'] ?? null,
                    'payment_status_description' => $data['payment_status_description'] ?? null,
                ];
            }

            return ['status' => 'UNKNOWN', 'error' => 'Failed to query status'];
        } catch (\Exception $e) {
            return ['status' => 'UNKNOWN', 'error' => $e->getMessage()];
        }
    }

    protected function normalizeResponse(array $res, string $identifier): TransactionResultDTO
    {
        if (isset($res['error'])) {
            return TransactionResultDTO::failure($res['error'], $res);
        }

        $success = in_array(strtoupper($res['status'] ?? 'PENDING'), ['COMPLETED', 'PENDING']);

        return TransactionResultDTO::success(
            transactionId: $res['order_tracking_id'] ?? '',
            merchantReference: $res['merchant_reference'] ?? $identifier,
            redirectUrl: $res['redirect_url'] ?? null,
            status: $res['status'] ?? 'PENDING',
            message: $success ? 'Payment initiated' : 'Payment failed',
            rawResponse: $res
        );
    }

    protected function normalizeError(string $errorCode, string $message): TransactionResultDTO
    {
        return TransactionResultDTO::failure($message, ['error_code' => $errorCode]);
    }

    /**
     * Refund a previously processed payment.
     */
    public function refund(string $transactionId, float $amount, string $reason = ''): TransactionResultDTO
    {
        try {
            $token = $this->getJwtToken();
            if (!$token) {
                return $this->normalizeError('authentication_failed', 'Failed to obtain JWT token');
            }

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::asJson()->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->post(
                $this->apiBase . '/Transactions/RefundRequest',
                [
                    'order_tracking_id' => $transactionId,
                    'amount' => (string) $amount,
                    'username' => config('app.name', 'Dadisi Community Labs'),
                    'remarks' => $reason ?: 'Refund requested per customer request',
                ]
            );

            if ($response->successful()) {
                $data = $response->json();
                
                // PesaPal API 3.0 returns success status in the message or success boolean
                $isSuccess = ($data['status'] ?? '') === '200' || ($data['success'] ?? false) || str_contains(strtolower($data['message'] ?? ''), 'accepted');

                if ($isSuccess) {
                    return TransactionResultDTO::success(
                        transactionId: $data['refund_id'] ?? $transactionId,
                        merchantReference: $transactionId,
                        status: 'REFUNDED',
                        message: $data['message'] ?? 'Refund processed successfully',
                        rawResponse: $data
                    );
                }
                
                return TransactionResultDTO::failure($data['message'] ?? 'Refund request failed', $data);
            }

            return TransactionResultDTO::failure(
                'Failed to connect to Pesapal for refund',
                $response->json() ?? ['body' => $response->body()]
            );

        } catch (\Exception $e) {
            \Log::error('Pesapal refund exception: ' . $e->getMessage());
            return $this->normalizeError('unknown_error', $e->getMessage());
        }
    }
}
