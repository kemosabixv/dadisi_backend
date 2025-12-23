<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\RenewalPreference;
use App\Models\SubscriptionEnhancement;
use App\Services\PaymentGatewayFactory;
use App\Services\MockPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @group Subscription Lifecycle
 * @groupDescription Core endpoints for managing the user's subscription state, including status checks, renewal preferences, and payment initiation.
 *
 * This group handles the primary business logic for a user's subscription journey, from checking their current status to initiating payments and managing renewals.
 */
class SubscriptionCoreController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get Current Subscription
     *
     * Retrieves detailed information about the authenticated user's currently active subscription.
     * Includes the plan details, subscription status, expiration dates, and any active enhancements (like grace periods).
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
    public function getCurrentSubscription(): JsonResponse
    {
        $user = auth()->user();

        Log::info('getCurrentSubscription called', [
            'user_id' => $user?->id,
            'active_subscription_id' => $user?->active_subscription_id ?? null,
        ]);

        $subscription = $user->activeSubscription()->with('plan:id,name,description,price')->first();
        $enhancement = $subscription ?
            SubscriptionEnhancement::where('subscription_id', $subscription->id)->first() :
            null;

        // Normalize plan to include raw JSON attributes for name/description
        $planData = null;
        if ($subscription && $subscription->plan) {
            $plan = $subscription->plan;
            $rawName = $plan->getRawOriginal('name');
            $rawDescription = $plan->getRawOriginal('description');

            $planData = [
                'id' => $plan->id,
                'name' => is_string($rawName) ? json_decode($rawName, true) ?? $plan->name : $rawName,
                'description' => is_string($rawDescription) ? json_decode($rawDescription, true) ?? $plan->description : $rawDescription,
                'price' => $plan->price,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'plan' => $planData,
                'subscription' => $subscription,
                'enhancement' => $enhancement,
            ],
        ]);
    }

    /**
     * Check Subscription Status
     *
     * Provides a high-level overview of the user's subscription status.
     * Returns the current status (e.g., active, expired, payment_failed), details of the active record, and any enhancement flags.
     * This is commonly used by the frontend to gate access to premium features.
     *
     * @group Subscription Lifecycle
     * @authenticated
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
        Log::info('getSubscriptionStatus called', [
            'user_id' => $user?->id,
            'active_subscription_id' => $user?->active_subscription_id ?? null,
        ]);
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
     * List Available Plans (Simplified)
     *
     * Retrieves a simplified list of active subscription plans.
     * This endpoint is often used for "Upgrade Plan" dropdowns or quick references where full promotional details are not required.
     *
     * @group Subscription Lifecycle
     * @authenticated
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
        $plans = Plan::where('is_active', true)
            ->select('id', 'name', 'description', 'price', 'invoice_period', 'invoice_interval')
            ->orderBy('price')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
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
     * Update Renewal Preferences
     *
     * Allows the user to configure how their subscription should be handled upon expiration.
     * Users can toggle auto-renewal, set reminder timing, or update their preferred payment method link.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @bodyParam renewal_type string optional The renewal mode: "automatic" or "manual". Example: automatic
     * @bodyParam send_renewal_reminders boolean optional Whether to receive email reminders before expiry. Example: true
     * @bodyParam reminder_days_before integer optional Number of days before expiry to send a reminder (1-30). Example: 7
     * @bodyParam preferred_payment_method string optional Identifier for the preferred payment method (e.g., "mpesa", "card"). Example: mpesa
     * @bodyParam auto_switch_to_free_on_expiry boolean optional If true, allows downgrade to free tier automatically if renewal fails. Example: true
     * @bodyParam notes string optional User notes regarding their renewal preference. Example: Prefer text reminders.
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
     * Initiate Subscription Payment
     *
     * Starts the payment process for purchasing or renewing a subscription plan.
     * This creates a pending subscription record and initiates a transaction with the payment gateway.
     * Returns a transaction reference that the frontend uses to track or verify the payment.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @bodyParam plan_id integer required The ID of the plan to subscribe to. Example: 1
     * @bodyParam billing_period string optional The billing cycle: "month" or "year". Default: month. Example: month
     * @bodyParam phone string optional The mobile number for payment (required for mobile money). Format: 254xxxxxxxxx. Example: 254712345678
     *
     * @response 201 {
     *   "success": true,
     *   "data": {
     *     "transaction_id": "MOCK_TRAN_987654",
     *     "redirect_url": "https://api.dadisilab.com/api/payments/mock/checkout?id=MOCK_TRAN_987654",
     *     "order_tracking_id": "ORDER_SUBS_001"
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

            // Create or update subscription with polymorphic relationship
            $baseTime = now();
            $subscription = PlanSubscription::updateOrCreate(
                ['subscriber_id' => $user->id, 'subscriber_type' => 'App\Models\User', 'plan_id' => $plan->id],
                [
                    'starts_at' => $baseTime,
                    'ends_at' => $billingPeriod === 'year' ? $baseTime->copy()->addDays(366) : $baseTime->copy()->addMonth(),
                    'trial_ends_at' => null,
                    'name' => $plan->name,
                    'slug' => $plan->slug . '-' . $user->id . '-' . time(),
                ]
            );

            Log::info('Subscription created/updated', [
                'subscription_id' => $subscription->id,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'billing_period' => $billingPeriod,
            ]);

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

            $paymentResponse = PaymentGatewayFactory::initiatePayment($paymentData);

            // Set the user's active subscription
            $user->update(['active_subscription_id' => $subscription->id]);

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
     * Process Mock Payment (Dev/Test)
     *
     * internal/Development endpoint to simulate payment completion.
     * This allows developers to manually trigger a "Success" or "Failure" outcome for a pending transaction without using a real payment provider.
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

        $user = auth()->user();

        try {
            DB::beginTransaction();

            Log::info('processMockPayment start', ['user_id' => $user->id]);
            // Find the subscription enhancement - use polymorphic query
            $subscription = PlanSubscription::where('subscriber_id', $user->id)
                ->where('subscriber_type', 'App\Models\User')
                ->latest()
                ->first();

            if (!$subscription) {
                throw new \Exception('No active subscription found');
            }

            $enhancement = SubscriptionEnhancement::where('subscription_id', $subscription->id)->first();

            if (!$enhancement) {
                throw new \Exception('No active subscription enhancement found');
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
     * Cancel Subscription
     *
     * Terminates the user's active subscription.
     * Depending on business logic, this may take effect immediately or at the end of the current billing cycle.
     * The subscription status will update to 'cancelled'.
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
        ]);

        $user = auth()->user();
        Log::info('cancelSubscription called', [
            'user_id' => $user?->id,
            'active_subscription_id' => $user?->active_subscription_id ?? null,
        ]);
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
                $enhancement->update(['status' => 'cancelled']);
            }

            // Update subscription - use the laravel-subscriptions fields
            $subscription->update([
                'canceled_at' => now(),
                'cancels_at' => now(),
            ]);

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
