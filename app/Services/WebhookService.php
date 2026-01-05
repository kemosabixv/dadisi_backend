<?php

namespace App\Services;

use App\Services\Contracts\WebhookServiceContract;
use App\Services\PaymentGateway\PesapalGateway;
use App\Models\WebhookEvent;
use App\Jobs\ProcessWebhookJob;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Service
 *
 * Handles webhook processing and event management for payment gateway integrations.
 */
class WebhookService implements WebhookServiceContract
{
    public function __construct(private PesapalGateway $pesapalGateway)
    {
    }

    /**
     * Process Pesapal webhook notification
     */
    public function processPesapalWebhook(array $payload, string $signature): array
    {
        try {
            Log::info('Received Pesapal webhook', [
                'payload' => $payload,
                'signature' => $signature,
            ]);

            // Populate external/order references from common keys
            $externalId = $payload['OrderTrackingId'] ?? $payload['reference'] ?? ('webhook_' . uniqid());
            $orderRef = $payload['OrderMerchantReference'] ?? $payload['reference'] ?? $externalId;

            $webhookEvent = WebhookEvent::create([
                'provider' => 'pesapal',
                'event_type' => $payload['OrderNotificationType'] ?? 'unknown',
                'external_id' => $externalId,
                'order_reference' => $orderRef,
                'payload' => $payload,
                'signature' => $signature,
                'status' => 'received',
            ]);

            // Verify security token - prevents unauthorized pings to this endpoint
            // The token is passed via query parameter during IPN registration
            $expectedToken = config('payment.pesapal.webhook_secret');
            $isSignatureValid = true;

            if ($expectedToken) {
                // We check if 'token' matches in payload (if passed via GET) or if passed explicitly
                $token = $payload['token'] ?? null;
                if ($token !== $expectedToken) {
                    $isSignatureValid = false;
                    Log::warning('Invalid Pesapal webhook token provided', ['provided' => $token]);
                }
            }

            if (!$isSignatureValid) {
                $webhookEvent->update([
                    'status' => 'failed',
                    'error' => 'Invalid security token',
                ]);
                throw new \Exception('Invalid security token');
            }

            // Dispatch background job for processing
            ProcessWebhookJob::dispatch($webhookEvent->id);
            Log::info('Webhook queued for processing', ['id' => $webhookEvent->id]);

            return ['status' => 'OK'];
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', ['error' => $e->getMessage()]);
            if (app()->bound('sentry')) {
                \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($payload) {
                    $scope->setContext('webhook_raw', $payload);
                });
                \Sentry\captureException($e);
            }
            throw $e;
        }
    }

    /**
     * List webhook events with optional filtering
     */
    public function listWebhookEvents(array $filters = []): LengthAwarePaginator
    {
        try {
            $query = WebhookEvent::query();

            if (!empty($filters['provider'])) {
                $query->where('provider', $filters['provider']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            return $query->orderBy('created_at', 'desc')
                ->paginate($filters['per_page'] ?? 50);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve webhook events', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
