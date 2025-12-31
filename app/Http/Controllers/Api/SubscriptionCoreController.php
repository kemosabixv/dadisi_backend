<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\SubscriptionCoreServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @group Subscription Lifecycle
 * @groupDescription Core endpoints for managing the user's subscription state, including status checks, renewal preferences, and payment initiation.
 */
class SubscriptionCoreController extends Controller
{
    public function __construct(
        private SubscriptionCoreServiceContract $subscriptionCoreService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get Current Subscription
     *
     * Retrieves detailed information about the authenticated user's currently active subscription.
     * Includes the plan details, subscription status, expiration dates, and any active enhancements.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "user_id": 2,
     *     "plan": {"id": 2, "name": "Premium Member", "price": 2500},
     *     "subscription": {"id": 1, "status": "active", "expires_at": "2026-12-06T00:00:00Z"},
     *     "enhancement": {"status": "active", "grace_period_ends_at": null}
     *   }
     * }
     */
    public function getCurrentSubscription(Request $request): JsonResponse
    {
        try {
            $data = $this->subscriptionCoreService->getCurrentSubscription($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $request->user()->id,
                    'plan' => $data['plan'] ?? null,
                    'subscription' => $data['subscription'] ?? null,
                    'enhancement' => $data['enhancement'] ?? null,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve subscription', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve subscription'], 500);
        }
    }

    /**
     * Check Subscription Status
     *
     * Provides a high-level overview of the user's subscription status.
     * Returns the current status, details of the active record, and any enhancement flags.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "current_status": "active",
     *     "status_details": {"status": "active", "expires_at": "2026-12-06"},
     *     "enhancements": [{"status": "active"}]
     *   }
     * }
     */
    public function getSubscriptionStatus(Request $request): JsonResponse
    {
        try {
            $data = $this->subscriptionCoreService->getSubscriptionStatus($request->user()->id);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Failed to get status', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve status'], 500);
        }
    }

    /**
     * List Available Plans (Simplified)
     *
     * Retrieves a simplified list of active subscription plans.
     * This endpoint is often used for "Upgrade Plan" dropdowns.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {"id": 1, "name": "Free", "price": 0, "features": ["Basic access"]}
     *   ]
     * }
     */
    public function getAvailablePlans(): JsonResponse
    {
        try {
            $plans = $this->subscriptionCoreService->getAvailablePlans();
            return response()->json(['success' => true, 'data' => $plans]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve plans', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve plans'], 500);
        }
    }

    /**
     * Get Renewal Preferences
     *
     * Retrieves the authenticated user's settings for subscription auto-renewal.
     * Includes preferences for reminders, payment methods, and fallback behaviors.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "user_id": 1,
     *     "renewal_type": "automatic",
     *     "send_renewal_reminders": true,
     *     "reminder_days_before": 7
     *   }
     * }
     */
    public function getRenewalPreferences(Request $request): JsonResponse
    {
        try {
            $preferences = $this->subscriptionCoreService->getRenewalPreferences($request->user()->id);
            return response()->json(['success' => true, 'data' => $preferences]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve preferences', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve preferences'], 500);
        }
    }

    /**
     * Update Renewal Preferences
     *
     * Allows the user to configure how their subscription should be handled upon expiration.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @bodyParam renewal_type string optional The renewal mode: "automatic" or "manual". Example: automatic
     * @bodyParam send_renewal_reminders boolean optional Whether to receive email reminders before expiry. Example: true
     * @bodyParam reminder_days_before integer optional Number of days before expiry to send a reminder (1-30). Example: 7
     * @bodyParam preferred_payment_method string optional Preferred payment method. Example: mpesa
     * @bodyParam auto_switch_to_free_on_expiry boolean optional Auto-downgrade if renewal fails. Example: true
     * @bodyParam notes string optional User notes. Example: Prefer text reminders.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Renewal preferences updated successfully",
     *   "data": {"renewal_type": "automatic"}
     * }
     */
    public function updateRenewalPreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'renewal_type' => 'nullable|in:automatic,manual',
            'send_renewal_reminders' => 'nullable|boolean',
            'reminder_days_before' => 'nullable|integer|min:1|max:30',
            'preferred_payment_method' => 'nullable|string|max:50',
            'auto_switch_to_free_on_expiry' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $preferences = $this->subscriptionCoreService->updateRenewalPreferences($request->user()->id, $validated);
            return response()->json([
                'success' => true,
                'message' => 'Renewal preferences updated successfully',
                'data' => $preferences,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update preferences', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to update preferences'], 500);
        }
    }

    /**
     * Initiate Subscription Payment
     *
     * Starts the payment process for purchasing or renewing a subscription plan.
     * This creates a pending subscription record and initiates a transaction with the payment gateway.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @bodyParam plan_id integer required The ID of the plan to subscribe to. Example: 1
     * @bodyParam billing_period string optional The billing cycle: "month" or "year". Default: month. Example: month
     * @bodyParam phone string optional The mobile number for payment (254xxxxxxxxx). Example: 254712345678
     *
     * @response 201 {
     *   "success": true,
     *   "data": {
     *     "transaction_id": "MOCK_TRAN_987654",
     *     "redirect_url": "https://api.dadisilab.com/api/payments/mock/checkout?id=MOCK_TRAN_987654"
     *   }
     * }
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_period' => 'nullable|in:month,year',
            'phone' => 'nullable|string|regex:/^254\d{9}$/',
            'payment_method' => 'nullable|string|in:pesapal,mock',
        ]);

        try {
            $result = $this->subscriptionCoreService->initiatePayment($request->user()->id, $validated);
            return response()->json(['success' => true, 'data' => $result], 201);
        } catch (\Exception $e) {
            Log::error('Payment initiation failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Payment initiation failed'], 500);
        }
    }

    /**
     * Process Mock Payment (Dev/Test)
     *
     * internal/Development endpoint to simulate payment completion.
     * Allows developers to manually trigger a "Success" or "Failure" outcome for a pending transaction.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @bodyParam transaction_id string required The transaction ID returned from the initiation step. Example: MOCK_abc123xyz
     * @bodyParam phone string required The phone number associated with the transaction. Example: 254712345678
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Payment processed successfully",
     *   "data": {"status": "completed"}
     * }
     */
    public function processMockPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => 'required|string',
            'phone' => 'required|string|regex:/^254\d{9}$/',
        ]);

        try {
            $result = $this->subscriptionCoreService->processMockPayment($request->user()->id, $validated);
            return response()->json([
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? 'Payment processed',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Mock payment failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Payment processing failed'], 500);
        }
    }

    /**
     * Cancel Subscription
     *
     * Terminates the user's active subscription.
     * Depending on business logic, this may take effect immediately or at the end of the current billing cycle.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @bodyParam reason string optional A reason for cancellation (for feedback). Example: Too expensive
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Subscription cancelled successfully"
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "No active subscription found"
     * }
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
            'immediate' => 'nullable|boolean',
        ]);

        try {
            $result = $this->subscriptionCoreService->cancelSubscription(
                $request->user()->id,
                $validated['reason'] ?? null
            );

            $statusCode = $result['success'] ? 200 : 404;
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Cancellation failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to cancel subscription'], 500);
        }
    }

    /**
     * Cancel a pending subscription payment.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Payment session cancelled successfully."
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "No pending payment found to cancel."
     * }
     */
    public function cancelSubscriptionPayment(Request $request): JsonResponse
    {
        try {
            $result = $this->subscriptionCoreService->cancelSubscriptionPayment(
                $request->input('subscription_id'),
                $request->input('reason')
            );
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Payment cancellation failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to cancel payment'], 500);
        }
    }
}

