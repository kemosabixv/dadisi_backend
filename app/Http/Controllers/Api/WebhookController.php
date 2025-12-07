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
     * Handle Pesapal webhook notifications
     *
     * @group Pesapal
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
            $webhookEvent = WebhookEvent::create([
                'provider' => 'pesapal',
                'event_type' => $payload['OrderNotificationType'] ?? 'unknown',
                'external_id' => $payload['OrderTrackingId'] ?? null,
                'order_reference' => $payload['OrderMerchantReference'] ?? null,
                'payload' => $payload,
                'signature' => $signature,
                'status' => 'received',
            ]);

            // Verify signature (skip if using mock)
            $isSignatureValid = true; // Allow for testing
            if (config('services.pesapal.environment') !== 'local' && app()->environment() !== 'local') {
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
     * Get webhook events (for debugging)
     *
     * @group Pesapal
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
