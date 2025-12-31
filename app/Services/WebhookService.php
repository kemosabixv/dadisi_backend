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

            // Verify signature - currently skipped in development
            // In production, signature should be verified against Pesapal's public key
            $isSignatureValid = true;
            // TODO: Implement signature verification when Pesapal public key is available
            // $isSignatureValid = $this->pesapalGateway->verifySignature($payload, $signature);

            if (!$isSignatureValid) {
                $webhookEvent->update([
                    'status' => 'failed',
                    'error' => 'Invalid signature',
                ]);
                Log::warning('Invalid Pesapal webhook signature');
                throw new \Exception('Invalid signature');
            }

            // Dispatch background job for processing
            ProcessWebhookJob::dispatch($webhookEvent->id);
            Log::info('Webhook queued for processing', ['id' => $webhookEvent->id]);

            return ['status' => 'OK'];
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', ['error' => $e->getMessage()]);
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
