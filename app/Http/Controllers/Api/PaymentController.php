<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanSubscription;
use App\Models\SubscriptionEnhancement;
use App\Services\MockPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payment Controller
 *
 * Manages payment processing, status checking, and webhook handling
 * Uses MockPaymentService for Phase 1 development/testing (Phase 1)
 */
class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['handleWebhook', 'checkPaymentStatus']);
    }

    /**
     * Get payment form metadata
     *
     * Retrieves configuration data required to render the payment form on the frontend.
     * Includes supported payment methods, currency limits, phone number formatting rules, and test numbers.
     * Use this to dynamically validate user input before submission.
     *
     * @group Payments
     * @groupDescription Endpoints for handling financial transactions, including initiating payments, verifying status, and viewing history. Also includes admin tools for refunds.
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "phone_format": "254XXXXXXXXX",
     *     "phone_example": "254712345678",
     *     "min_amount": 0.01,
     *     "max_amount": 999999.99,
     *     "currency": "KES",
     *     "supported_payment_methods": ["mobile_money", "card"]
     *   }
     * }
     */
    public function getPaymentFormMetadata(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'phone_format' => '254XXXXXXXXX',
                'phone_example' => '254712345678',
                'min_amount' => 0.01,
                'max_amount' => 999999.99,
                'currency' => 'KES',
                'supported_payment_methods' => ['mobile_money', 'card'],
                'test_success_phones' => ['254701234567', '254702-254705 range'],
                'test_failure_phones' => ['254709999999'],
                'test_pending_phones' => ['254707777777'],
            ],
        ]);
    }

    /**
     * Check payment status
     *
     * Queries the external payment gateway (or mock service) for the current status of a transaction.
     * Can be called publically to support callback/redirect pages where auth might be tricky, or for quick status checks.
     *
     * @group Payments
     *
     * @queryParam transaction_id string required The unique transaction ID provided during initiation. Example: MOCK_abc123xyz
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "transaction_id": "MOCK_abc123xyz",
     *     "status": "completed",
     *     "amount": 100.00,
     *     "currency": "KES"
     *   }
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "Transaction not found"
     * }
     */
    public function checkPaymentStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => 'required|string',
        ]);

        try {
            $status = MockPaymentService::queryPaymentStatus($validated['transaction_id']);

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment status check failed', [
                'transaction_id' => $validated['transaction_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify payment and update subscription
     *
     * Verifies a completed payment securely and updates the user's subscription status.
     * This endpoint should be called after a successful payment flow to ensure the local database reflects the new state.
     * It handles success, pending, and failure states, updating the subscription enhancement record accordingly.
     *
     * @group Payments
     * @authenticated
     *
     * @bodyParam transaction_id string required The transaction ID to verify. Example: MOCK_abc123xyz
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Payment verified and subscription activated",
     *   "data": {
     *     "subscription_id": 1,
     *     "status": "active",
     *     "payment_status": "completed"
     *   }
     * }
     * @response 500 {
     *   "success": false,
     *   "message": "Payment verification failed"
     * }
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => 'required|string',
        ]);

        $user = auth()->user();

        try {
            DB::beginTransaction();

            // Query payment status from mock service
            $paymentStatus = MockPaymentService::queryPaymentStatus($validated['transaction_id']);

            // Find the subscription associated with this payment
            $subscription = PlanSubscription::where('user_id', $user->id)
                ->latest()
                ->first();

            if (!$subscription) {
                throw new \Exception('No subscription found for this payment');
            }

            $enhancement = SubscriptionEnhancement::where('subscription_id', $subscription->id)->first();

            if (!$enhancement) {
                throw new \Exception('No subscription enhancement found');
            }

            // Update based on payment status
            if ($paymentStatus['status'] === 'completed' || $paymentStatus['status'] === 'success') {
                // Payment successful
                $enhancement->update([
                    'status' => 'active',
                    'payment_failure_state' => null,
                    'renewal_attempts' => 0,
                ]);

                $subscription->update(['status' => 'active']);

                $user->update([
                    'subscription_status' => 'active',
                    'subscription_activated_at' => now(),
                    'last_payment_date' => now(),
                    'active_subscription_id' => $subscription->id,
                ]);

                $message = 'Payment verified and subscription activated';
                $success = true;

            } elseif ($paymentStatus['status'] === 'pending') {
                // Payment still pending
                $enhancement->update(['status' => 'payment_pending']);
                $user->update(['subscription_status' => 'payment_pending']);

                $message = 'Payment is still processing';
                $success = true;

            } else {
                // Payment failed
                $failureState = $paymentStatus['status'] === 'timeout'
                    ? 'retry_delayed'
                    : 'retry_immediate';

                $enhancement->markPaymentFailed(
                    $failureState,
                    $paymentStatus['error_message'] ?? 'Payment verification failed'
                );

                $user->update(['subscription_status' => 'payment_failed']);

                $message = 'Payment verification failed';
                $success = false;
            }

            DB::commit();

            Log::info('Payment verified', [
                'user_id' => $user->id,
                'transaction_id' => $validated['transaction_id'],
                'status' => $paymentStatus['status'],
            ]);

            return response()->json([
                'success' => $success,
                'message' => $message,
                'data' => [
                    'subscription_id' => $subscription->id,
                    'status' => $enhancement->status,
                    'payment_status' => $paymentStatus['status'],
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment verification failed', [
                'user_id' => $user->id,
                'transaction_id' => $validated['transaction_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process payment (Simulation)
     *
     * Simulates the payment processing step for development and testing.
     * Allows forcing different outcomes (success, timeout, failure) based on the phone number provided.
     * See `getPaymentFormMetadata` for test numbers.
     *
     * @group Payments
     * @authenticated
     *
     * @bodyParam transaction_id string required Unique transaction reference. Example: MOCK_abc123xyz
     * @bodyParam order_id integer required The ID of the subscription order being paid for. Example: 1
     * @bodyParam phone string required MPESA phone number. Example: 254712345678
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Payment processed successfully",
     *   "data": {
     *     "status": "completed",
     *     "subscription_status": "active",
     *     "transaction_id": "MOCK_abc123xyz"
     *   }
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "phone": ["The phone format is invalid."]
     *   }
     * }
     */
    public function processPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => 'required|string',
            'order_id' => 'required|integer|exists:plan_subscriptions,id',
            'phone' => 'required|string|regex:/^254\d{9}$/',
        ]);

        $user = auth()->user();

        try {
            DB::beginTransaction();

            // Verify order belongs to user
            $subscription = PlanSubscription::where('id', $validated['order_id'])
                ->where('user_id', $user->id)
                ->firstOrFail();

            // Process payment
            $paymentResult = MockPaymentService::processPayment(
                $validated['phone'],
                [
                    'transaction_id' => $validated['transaction_id'],
                    'order_id' => $validated['order_id'],
                ]
            );

            $enhancement = SubscriptionEnhancement::where('subscription_id', $subscription->id)->first();

            if (!$enhancement) {
                throw new \Exception('Subscription enhancement not found');
            }

            // Update subscription based on payment result
            if ($paymentResult['success']) {
                $enhancement->update([
                    'status' => 'active',
                    'payment_failure_state' => null,
                ]);

                $subscription->update(['status' => 'active']);

                $user->update([
                    'subscription_status' => 'active',
                    'subscription_activated_at' => now(),
                    'last_payment_date' => now(),
                    'active_subscription_id' => $subscription->id,
                ]);

                $responseMessage = 'Payment processed successfully';
            } elseif ($paymentResult['status'] === 'pending') {
                $enhancement->update(['status' => 'payment_pending']);
                $user->update(['subscription_status' => 'payment_pending']);
                $responseMessage = 'Payment is being processed';
            } else {
                $failureState = $paymentResult['status'] === 'timeout'
                    ? 'retry_delayed'
                    : 'retry_immediate';

                $enhancement->markPaymentFailed(
                    $failureState,
                    $paymentResult['error_message'] ?? 'Payment processing failed'
                );

                $user->update(['subscription_status' => 'payment_failed']);
                $responseMessage = 'Payment failed';
            }

            DB::commit();

            Log::info('Payment processed', [
                'user_id' => $user->id,
                'transaction_id' => $validated['transaction_id'],
                'order_id' => $validated['order_id'],
                'success' => $paymentResult['success'],
            ]);

            return response()->json([
                'success' => $paymentResult['success'],
                'message' => $responseMessage,
                'data' => [
                    'status' => $paymentResult['status'],
                    'subscription_status' => $enhancement->status,
                    'transaction_id' => $validated['transaction_id'],
                    'order_id' => $validated['order_id'],
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment processing failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's payment history
     *
     * Retrieves a paginated history of all subscription payments made by the authenticated user.
     * Includes successful, failed, and pending transactions.
     *
     * @group Payments
     * @authenticated
     *
     * @queryParam per_page integer Number of records per page. Default: 15. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "subscription_id": 1,
     *       "plan_name": "Premium Monthly",
     *       "amount": 999.00,
     *       "currency": "KES",
     *       "status": "active",
     *       "failure_state": null,
     *       "started_at": "2025-12-01T10:00:00Z",
     *       "ended_at": "2026-01-01T10:00:00Z"
     *     }
     *   ],
     *   "pagination": {
     *     "total": 1,
     *     "per_page": 15,
     *     "current_page": 1
     *   }
     * }
     */
    public function getPaymentHistory(Request $request): JsonResponse
    {
        $user = auth()->user();
        $perPage = $request->input('per_page', 15);

        $subscriptions = $user->subscriptions()
            ->with('plan', 'enhancements')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $history = [];
        foreach ($subscriptions as $subscription) {
            foreach ($subscription->enhancements as $enhancement) {
                $history[] = [
                    'subscription_id' => $subscription->id,
                    'plan_name' => $subscription->plan?->name,
                    'amount' => $subscription->plan?->price,
                    'currency' => 'KES',
                    'status' => $enhancement->status,
                    'failure_state' => $enhancement->payment_failure_state,
                    'started_at' => $subscription->starts_at,
                    'ended_at' => $subscription->ends_at,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $history,
            'pagination' => [
                'total' => $subscriptions->total(),
                'per_page' => $subscriptions->perPage(),
                'current_page' => $subscriptions->currentPage(),
            ],
        ]);
    }

    /**
     * Handle payment webhook
     *
     * Receives instant payment notifications (IPN) from the payment gateway (e.g., Pesapal).
     * Updates local transaction status in real-time.
     * Note: This endpoint is public but verifies the payload signature/validity internally (mocked in Phase 1).
     *
     * @group Payments
     *
     * @bodyParam event_type string required Type of event. Example: payment.completed
     * @bodyParam transaction_id string required Gateway transaction ID. Example: MOCK_abc123xyz
     * @bodyParam status string required Status of payment. Example: completed
     * @bodyParam amount float Amount paid. Example: 100.00
     * @bodyParam order_tracking_id string optional Tracking ID. Example: ORDER_def456
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Webhook processed successfully"
     * }
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'required|string',
            'transaction_id' => 'required|string',
            'status' => 'required|in:completed,pending,failed',
            'amount' => 'nullable|numeric',
            'order_tracking_id' => 'nullable|string',
        ]);

        try {
            Log::info('Payment webhook received', $validated);

            // In Phase 1, we just log the webhook
            // In Phase 2, this would trigger automatic renewal logic

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Refund payment (admin only)
     *
     * Initiates a refund for a completed transaction.
     * RESTRICTED: Only accessible by administrators.
     * Useful for correcting billing errors or handling customer cancellations.
     *
     * @group Payments
     * @authenticated
     *
     * @bodyParam transaction_id string required The transaction ID to refund. Example: MOCK_abc123xyz
     * @bodyParam reason string required Justification for the refund. Example: Duplicate charge
     * @bodyParam amount float optional Partial refund amount. If omitted, refunds full amount. Example: 50.00
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Refund processed successfully",
     *   "data": {
     *     "refund_id": "REF_123",
     *     "status": "completed",
     *     "amount": 100.00
     *   }
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "Unauthorized - admin only"
     * }
     */
    public function refundPayment(Request $request): JsonResponse
    {
        // Check admin authorization
        if (!auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - admin only',
            ], 403);
        }

        $validated = $request->validate([
            'transaction_id' => 'required|string',
            'reason' => 'required|string|max:500',
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        try {
            $refundResult = MockPaymentService::refundPayment(
                $validated['transaction_id'],
                $validated['amount'] ?? null
            );

            Log::info('Refund processed', [
                'admin_id' => auth()->user()->id,
                'transaction_id' => $validated['transaction_id'],
                'reason' => $validated['reason'],
                'refund_result' => $refundResult,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => $refundResult,
            ]);

        } catch (\Exception $e) {
            Log::error('Refund processing failed', [
                'admin_id' => auth()->user()->id,
                'transaction_id' => $validated['transaction_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Refund processing failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
