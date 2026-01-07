<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\WebhookServiceContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private WebhookServiceContract $webhookService
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
     * @queryParam token string optional Security token for verification. Example: secret_token_123
    * @bodyParam OrderTrackingId string optional External tracking id provided by Pesapal. Example: 12345-abc
    * @bodyParam OrderMerchantReference string optional Merchant reference or order id. Example: ORD-98765
    * @bodyParam reference string optional Generic reference field sometimes used by providers. Example: ref_123
    * @bodyParam OrderNotificationType string optional Notification type (e.g., COMPLETED, FAILED). Example: COMPLETED
     *
     * @response 200 {
     *   "status": "OK"
     * }
     * @response 400 {
     *   "error": "Invalid security token"
     * }
    * @response 404 {
    *   "error": "Payment not found"
    * }
    * @response 500 {
     *   "error": "Processing failed"
     * }
     */
    public function pesapal(Request $request)
    {
        try {
            // Include query parameters (like 'token') in the payload
            $payload = array_merge($request->query(), $request->all());
            $signature = $request->header('X-Pesapal-Signature', '');

            $result = $this->webhookService->processPesapalWebhook($payload, $signature);

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', ['error' => $e->getMessage()]);
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
        try {
            $filters = [
                'provider' => $request->input('provider'),
                'status' => $request->input('status'),
                'event_type' => $request->input('event_type'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'order_by' => $request->input('order_by'),
                'order_dir' => $request->input('order_dir'),
                'per_page' => $request->input('per_page', 50),
            ];

            $events = $this->webhookService->listWebhookEvents($filters);

            return response()->json([
                'success' => true,
                'data' => $events,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve webhook events', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve webhook events'], 500);
        }
    }
}
