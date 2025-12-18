<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Services\PesapalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected PesapalService $pesapalService
    ) {}

    /**
     * Handle Pesapal Webhook
     *
     * Processes incoming webhook notifications from the Pesapal payment gateway.
     * This endpoint receives payment status updates (e.g., COMPLETED, FAILED) and synchronizes the local system state.
     * It validates the request signature to ensure authenticity before processing.
     *
     * @group Integrations - Pesapal
     * @groupDescription Endpoints for handling external callbacks and system integrations, specifically the Pesapal payment gateway.
     * @unauthenticated
     *
     * @response 200 {
     *   "status": "OK"
     * }
     * @response 400 {
     *   "error": "Invalid signature"
     * }
     * @response 500 {
     *   "error": "Processing failed"
     * }
     */
    public function pesapal(Request $request)
    {
        try {
            $payload = $request->all();
            $signature = $request->header('X-Pesapal-Signature', '');

            Log::info('Received Pesapal webhook', [
                'headers' => $request->headers->all(),
                'payload' => $payload,
                'signature' => $signature,
            ]);

            // Store the webhook event regardless of verification
            // Populate external/order references from common keys; ensure non-null for DB constraints
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

            // Verify signature (skip in local/testing environments or when services.pesapal.environment is 'local')
            $isSignatureValid = true; // default to true for non-production
            if (!app()->environment('local', 'testing') && config('services.pesapal.environment') !== 'local') {
                $isSignatureValid = $this->pesapalService->verifyWebhookSignature($payload, $signature);
            }

            if (!$isSignatureValid) {
                $webhookEvent->update([
                    'status' => 'failed',
                    'error' => 'Invalid signature',
                ]);

                Log::warning('Invalid Pesapal webhook signature');

                return response()->json(['error' => 'Invalid signature'], 400);
            }

            // Process the webhook
            $result = $this->pesapalService->processWebhook($payload);

            // Update webhook event status
            $webhookEvent->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error' => $result['status'] === 'payment_not_found' ? 'Payment not found' : null,
            ]);

            // Return appropriate response based on result
            if ($result['status'] === 'payment_not_found') {
                Log::warning('Webhook processed but payment not found', ['result' => $result]);
                return response()->json(['error' => 'Payment not found'], 404);
            }

            Log::info('Webhook processed successfully', ['result' => $result]);

            // Pesapal expects HTTP 200 OK on success
            return response()->json(['status' => 'OK'], 200);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'exception' => $e->getMessage(),
                'payload' => $payload,
            ]);

            // Store failed event if we have the ID
            if (isset($webhookEvent)) {
                $webhookEvent->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * List Webhooks (Debug)
     *
     * Retrieves a paginated history of received webhook events.
     * Useful for debugging integration issues and verifying payment callbacks.
     *
     * @group Integrations - Pesapal
     * @authenticated
     *
     * @queryParam provider string Filter by provider name. Example: pesapal
     * @queryParam status string Filter by processing status (received, processed, failed). Example: failed
     * @queryParam per_page integer Pagination limit. Example: 20
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 1,
     *         "provider": "pesapal",
     *         "event_type": "COMPLETED",
     *         "status": "processed",
     *         "created_at": "2025-12-04T10:00:00Z"
     *       }
     *     ]
     *   }
     * }
     */
    public function index(Request $request)
    {
        $events = WebhookEvent::query()
            ->when($request->provider, fn($q) => $q->where('provider', $request->provider))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }
}
