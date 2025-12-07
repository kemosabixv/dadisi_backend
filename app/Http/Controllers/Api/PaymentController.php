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
     * @group Payments
     * @authenticated
     * @description Get payment form details and validation rules for UI rendering
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "phone_format": "254XXXXXXXXX",
     *     "min_amount": 0.01,
     *     "max_amount": 999999.99,
     *     "supported_methods": ["mobile_money"]
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
     * @group Payments
     * @description Check the status of a payment transaction (unauthenticated)
     *
     * @queryParam transaction_id string required The transaction ID to check. Example: MOCK_abc123xyz
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "transaction_id": "MOCK_abc123xyz",
     *     "status": "completed",
     *     "amount": 100.00
     *   }
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
     * @group Payments
     * @authenticated
     * @description Verify payment completion and update subscription status
     *
     * @bodyParam transaction_id string required Transaction ID to verify. Example: MOCK_abc123xyz
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Payment verified and subscription activated",
     *   "data": {"subscription_id": 1, "status": "active"}
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
     * Process payment with phone number simulation
     *
     * @group Payments
     * @authenticated
     * @description Complete payment processing with phone number to simulate outcomes
     *
     * @bodyParam transaction_id string required Transaction ID from initiation. Example: MOCK_abc123xyz
     * @bodyParam order_id integer Order/Subscription ID. Example: 1
     * @bodyParam phone string required Phone number for outcome simulation. Example: 254712345678
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Payment processed successfully",
     *   "data": {"status": "completed", "subscription_status": "active"}
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
     * @group Payments
     * @authenticated
     * @description Retrieve user's payment transaction history
     *
     * @queryParam per_page integer Results per page. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {"transaction_id": "MOCK_abc", "amount": 100, "status": "completed", "date": "2025-12-06"}
     *   ]
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
     * Mock webhook for payment completion
     *
     * @group Payments
     * @description Webhook endpoint for Pesapal payment notifications (Phase 1 uses mock)
     *
     * @bodyParam event_type string Event type from payment gateway. Example: payment.completed
     * @bodyParam transaction_id string Transaction ID from payment gateway. Example: MOCK_abc123xyz
     * @bodyParam status string Payment status. Example: completed
     * @bodyParam amount float Payment amount. Example: 100.00
     * @bodyParam order_tracking_id string Order tracking ID. Example: ORDER_def456
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
     * @group Payments
     * @authenticated
     * @description Process refund for a payment transaction
     *
     * @bodyParam transaction_id string required Transaction ID to refund. Example: MOCK_abc123xyz
     * @bodyParam reason string required Refund reason. Example: Duplicate charge
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Refund processed successfully",
     *   "data": {"refund_id": "REF_123", "status": "completed"}
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
