<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\RenewalPreference;
use App\Models\SubscriptionEnhancement;
use App\Services\MockPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Subscription Core Controller
 *
 * Manages subscription lifecycle including creation, status,
 * renewal preferences, and payment initiation (Phase 1)
 */
class SubscriptionCoreController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get current user's subscription
     *
     * @group Subscriptions - Core
     * @authenticated
     * @description Get detailed information about the authenticated user's active subscription
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "user_id": 1,
     *     "plan": {"id": 1, "name": "Premium", "price": 99.99},
     *     "subscription": {"id": 1, "status": "active", "expires_at": "2026-12-06"},
     *     "enhancement": {"status": "active", "grace_period_ends_at": null}
     *   }
     * }
     */
    public function getCurrentSubscription(): JsonResponse
    {
        $user = auth()->user();

        $subscription = $user->activeSubscription()->with('plan:id,name,description,price')->first();
        $enhancement = $subscription ?
            SubscriptionEnhancement::where('subscription_id', $subscription->id)->first() :
            null;

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'plan' => $subscription?->plan,
                'subscription' => $subscription,
                'enhancement' => $enhancement,
            ],
        ]);
    }

    /**
     * Get subscription status and history
     *
     * @group Subscriptions - Core
     * @authenticated
     * @description Retrieve subscription status, history, and enhancement details
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "current_status": "active",
     *     "status_details": {"status": "active", "expires_at": "2026-12-06"},
     *     "enhancements": [{"status": "active", "renewal_attempts": 0}],
     *     "history": []
     *   }
     * }
     */
    public function getSubscriptionStatus(): JsonResponse
    {
        $user = auth()->user();
        $subscription = $user->activeSubscription()->first();

        $enhancements = $subscription ?
            SubscriptionEnhancement::where('subscription_id', $subscription->id)->get() :
            [];

        return response()->json([
            'success' => true,
            'data' => [
                'current_status' => $user->subscription_status ?? 'none',
                'status_details' => $subscription,
                'enhancements' => $enhancements,
                'history' => [], // Will be populated in Phase 2
            ],
        ]);
    }

    /**
     * Get available plans
     *
     * @group Subscriptions - Core
     * @authenticated
     * @description List all available subscription plans with pricing and features
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Free",
     *       "price": 0,
     *       "billing_period": "month",
     *       "features": ["Basic access"]
     *     }
     *   ]
     * }
     */
    public function getAvailablePlans(): JsonResponse
    {
        $plans = Plan::where('active', true)
            ->select('id', 'name', 'description', 'price', 'billing_period')
            ->orderBy('price')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * Get renewal preferences
     *
     * @group Subscriptions - Core
     * @authenticated
     * @description Get or create the authenticated user's renewal preferences
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
    public function getRenewalPreferences(): JsonResponse
    {
        $user = auth()->user();
        $preferences = $user->getOrCreateRenewalPreferences();

        return response()->json([
            'success' => true,
            'data' => $preferences,
        ]);
    }

    /**
     * Update renewal preferences
     *
     * @group Subscriptions - Core
     * @authenticated
     * @description Update user's renewal preferences (auto/manual, reminders, etc.)
     *
     * @bodyParam renewal_type string Renewal type: "automatic" or "manual". Example: automatic
     * @bodyParam send_renewal_reminders boolean Send renewal reminder emails. Example: true
     * @bodyParam reminder_days_before integer Days before expiry to send reminder. Example: 7
     * @bodyParam preferred_payment_method string Preferred payment method. Example: mobile_money
     * @bodyParam auto_switch_to_free_on_expiry boolean Auto-downgrade to Free plan on expiry. Example: true
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

        $user = auth()->user();
        $preferences = $user->getOrCreateRenewalPreferences();
        $preferences->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Renewal preferences updated successfully',
            'data' => $preferences,
        ]);
    }

    /**
     * Initiate payment for subscription
     *
     * @group Subscriptions - Core
     * @authenticated
     * @description Start the payment process for a subscription plan using mock gateway
     *
     * @bodyParam plan_id integer required The plan ID to subscribe to. Example: 1
     * @bodyParam billing_period string Billing period: "month" or "year". Example: month
     * @bodyParam phone string Phone number for payment processing. Example: 254712345678
     *
     * @response 201 {
     *   "success": true,
     *   "data": {
     *     "transaction_id": "MOCK_abc123xyz",
     *     "redirect_url": "http://localhost:8000/api/payments/mock/checkout?...",
     *     "order_tracking_id": "ORDER_def456"
     *   }
     * }
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_period' => 'nullable|in:month,year',
            'phone' => 'nullable|string|regex:/^254\d{9}$/',
        ]);

        $user = auth()->user();
        $plan = Plan::findOrFail($validated['plan_id']);
        $billingPeriod = $validated['billing_period'] ?? 'month';

        try {
            DB::beginTransaction();

            // Create or update subscription
            $subscription = PlanSubscription::firstOrCreate(
                ['user_id' => $user->id, 'plan_id' => $plan->id],
                [
                    'starts_at' => now(),
                    'ends_at' => $billingPeriod === 'year' ? now()->addYear() : now()->addMonth(),
                    'trial_ends_at' => null,
                ]
            );

            // Create or update enhancement
            $enhancement = SubscriptionEnhancement::firstOrCreate(
                ['subscription_id' => $subscription->id],
                [
                    'status' => 'payment_pending',
                    'max_renewal_attempts' => 3,
                    'metadata' => json_encode(['billing_period' => $billingPeriod]),
                ]
            );

            // Initiate mock payment
            $paymentData = [
                'plan_id' => $plan->id,
                'user_id' => $user->id,
                'order_id' => $subscription->id,
                'amount' => $plan->price,
                'currency' => 'KES',
                'billing_period' => $billingPeriod,
            ];

            $paymentResponse = MockPaymentService::initiatePayment($paymentData);

            DB::commit();

            Log::info('Payment initiated for subscription', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'transaction_id' => $paymentResponse['transaction_id'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $paymentResponse,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment initiation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process mock payment (for testing only)
     *
     * @group Subscriptions - Core
     * @authenticated
     * @description Process a mock payment with controlled success/failure outcomes
     *
     * @bodyParam transaction_id string required The transaction ID from payment initiation. Example: MOCK_abc123xyz
     * @bodyParam phone string Phone number for simulating payment outcome. Example: 254712345678
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

        $user = auth()->user();

        try {
            DB::beginTransaction();

            // Find the subscription enhancement
            $enhancement = SubscriptionEnhancement::where('subscription_id',
                PlanSubscription::where('user_id', $user->id)->latest()->value('id')
            )->first();

            if (!$enhancement) {
                throw new \Exception('No active subscription found');
            }

            // Process mock payment
            $paymentResult = MockPaymentService::processPayment(
                $validated['phone'],
                ['transaction_id' => $validated['transaction_id']]
            );

            // Update subscription status based on payment result
            if ($paymentResult['success']) {
                $enhancement->update([
                    'status' => 'active',
                    'payment_failure_state' => null,
                ]);

                $user->update([
                    'subscription_status' => 'active',
                    'subscription_activated_at' => now(),
                    'last_payment_date' => now(),
                ]);

                $message = 'Payment processed successfully';
            } else {
                $failureState = $paymentResult['status'] === 'pending'
                    ? 'retry_delayed'
                    : 'retry_immediate';

                $enhancement->markPaymentFailed($failureState, $paymentResult['error_message'] ?? 'Payment failed');

                $user->update(['subscription_status' => 'payment_failed']);

                $message = 'Payment failed. Please try again.';
            }

            DB::commit();

            return response()->json([
                'success' => $paymentResult['success'],
                'message' => $message,
                'data' => $paymentResult,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mock payment processing failed', [
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
     * Cancel subscription
     *
     * @group Subscriptions - Core
     * @authenticated
     * @description Cancel the authenticated user's active subscription
     *
     * @bodyParam reason string Cancellation reason. Example: No longer needed
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Subscription cancelled successfully"
     * }
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $subscription = $user->activeSubscription()->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Cancel enhancement
            $enhancement = SubscriptionEnhancement::where('subscription_id', $subscription->id)->first();
            if ($enhancement) {
                $enhancement->cancel();
            }

            // Update subscription
            $subscription->update(['status' => 'cancelled']);

            // Update user
            $user->update([
                'subscription_status' => 'cancelled',
                'active_subscription_id' => null,
            ]);

            DB::commit();

            Log::info('Subscription cancelled', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'reason' => $validated['reason'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Subscription cancellation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
