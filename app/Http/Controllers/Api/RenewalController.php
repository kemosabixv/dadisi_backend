<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanSubscription;
use App\Models\RenewalReminder;
use App\Services\RenewalReminderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class RenewalController extends Controller
{
    protected RenewalReminderService $reminderService;

    public function __construct(RenewalReminderService $reminderService)
    {
        $this->middleware('auth:sanctum');
        $this->reminderService = $reminderService;
    }
    /**
     * Request manual renewal for a subscription (user-initiated)
     *
     * This endpoint triggers the manual renewal workflow for a specific subscription.
     * It schedules renewal reminders and prepares the system for a user-initiated payment.
     * Use this when a user wants to renew before the automatic process kicks in or if auto-renewal failed.
     *
     * @group Subscriptions - Renewals
     * @groupDescription Endpoints for users to manage their subscription renewals, including requesting manual renewals, viewing payment options, and checking renewal reminders.
     * @authenticated
     * @urlParam id integer required The unique ID of the subscription to renew. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Manual renewal requested. Reminders scheduled."
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "Subscription not found"
     * }
     */
    public function requestManualRenewal(Request $request, $subscriptionId): JsonResponse
    {
        $user = $request->user();
        $subscription = PlanSubscription::where('id', $subscriptionId)->where('user_id', $user->id)->first();

        if (!$subscription) {
            return response()->json(['success' => false, 'message' => 'Subscription not found'], 404);
        }

        // For Phase 2 we only create a renewal job record and return options
        // Real payment handling will be in PaymentController / AutoRenewalService
        try {
            $this->reminderService->scheduleRemindersForSubscription($subscription);

            return response()->json(['success' => true, 'message' => 'Manual renewal requested. Reminders scheduled.']);
        } catch (\Exception $e) {
            Log::error('Manual renewal request failed', ['error' => $e->getMessage(), 'subscription_id' => $subscriptionId]);
            return response()->json(['success' => false, 'message' => 'Failed to request renewal'], 500);
        }
    }

    /**
     * Get renewal payment options for a subscription
     *
     * Fetches the valid payment options, current pricing, and supported currencies for renewing a specific subscription.
     * This ensures the frontend displays up-to-date payment channels (e.g., M-Pesa, Card) and accurate amounts.
     *
     * @group Subscriptions - Renewals
     * @authenticated
     * @urlParam id integer required The unique ID of the subscription. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "payment_methods": [
     *       {"type": "mobile_money", "display": "M-Pesa (Mobile Money)"},
     *       {"type": "card", "display": "Card (Mock)"}
     *     ],
     *     "amount": 2500,
     *     "currency": "KES"
     *   }
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "Subscription not found"
     * }
     */
    public function getManualRenewalOptions(Request $request, $subscriptionId): JsonResponse
    {
        $user = $request->user();
        $subscription = PlanSubscription::where('id', $subscriptionId)->where('user_id', $user->id)->with('plan')->first();

        if (!$subscription) {
            return response()->json(['success' => false, 'message' => 'Subscription not found'], 404);
        }

        // Placeholder: return available payment channels
        return response()->json([
            'success' => true,
            'data' => [
                'payment_methods' => [
                    ['type' => 'mobile_money', 'display' => 'M-Pesa (Mobile Money)'],
                    ['type' => 'card', 'display' => 'Card (Mock)'],
                ],
                'amount' => $subscription->plan->price ?? 0,
                'currency' => $subscription->plan->currency ?? 'KES',
            ],
        ]);
    }

    /**
     * Confirm manual renewal (placeholder)
     *
     * Confirms the user's intent to renew using a specific payment method.
     * In a real-world scenario, this would initiate the payment gateway flow or create a pending renewal job.
     * Currently acts as a confirmation step returning success for mock purposes.
     *
     * @group Subscriptions - Renewals
     * @authenticated
     * @urlParam id integer required The unique ID of the subscription. Example: 1
     * @bodyParam payment_method string required The payment method chosen by the user. Example: mobile_money
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Renewal confirmed (mock)"
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "payment_method": ["The payment method field is required."]
     *   }
     * }
     */
    public function confirmManualRenewal(Request $request, $subscriptionId): JsonResponse
    {
        $user = $request->user();
        $subscription = PlanSubscription::where('id', $subscriptionId)->where('user_id', $user->id)->with('plan')->first();

        if (!$subscription) {
            return response()->json(['success' => false, 'message' => 'Subscription not found'], 404);
        }

        $validated = $request->validate([
            'payment_method' => 'required|string',
        ]);

        // Create an AutoRenewalJob record if needed in Phase 2 (skipped here)
        return response()->json(['success' => true, 'message' => 'Renewal confirmed (mock)']);
    }

    /**
     * List pending reminders
     *
     * Retrieves a list of scheduled renewal reminders that haven't been sent yet for the authenticated user.
     * Useful for showing users when they will be notified about upcoming expirations.
     *
     * @group Subscriptions - Renewals
     * @authenticated
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "scheduled_at": "2025-01-15T09:00:00Z",
     *       "is_sent": false
     *     }
     *   ]
     * }
     */
    public function getPendingReminders(Request $request): JsonResponse
    {
        $user = $request->user();

        $reminders = RenewalReminder::where('user_id', $user->id)
            ->where('is_sent', false)
            ->where('scheduled_at', '<=', now()->addDays(30))
            ->orderBy('scheduled_at')
            ->get();

        return response()->json(['success' => true, 'data' => $reminders]);
    }

    /**
     * Admin: Extend grace period for a subscription
     *
     * Allows administrators to grant additional time (grace period) to a subscription that has expired or is about to expire.
     * This is typically used in customer support scenarios where a user has a valid reason for delayed payment.
     *
     * @group Subscriptions - Renewals (Admin)
     * @groupDescription Administrative endpoints for managing subscription lifecycles, specifically handling manual overrides like grace period extensions.
     * @authenticated
     * @urlParam subscriptionId integer required The unique ID of the subscription. Example: 10
     * @bodyParam days integer required Number of days to extend the grace period (1-90). Example: 14
     * @bodyParam note string optional Reason for the extension (for audit logs). Example: User promised payment next week.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Grace period extended"
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "Unauthorized"
     * }
     */
    public function extendGracePeriod(Request $request, $subscriptionId): JsonResponse
    {
        $user = $request->user();

        if (Gate::denies('manage-subscriptions')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:90',
            'note' => 'nullable|string|max:500',
        ]);

        $subscription = PlanSubscription::find($subscriptionId);
        if (!$subscription) {
            return response()->json(['success' => false, 'message' => 'Subscription not found'], 404);
        }

        $enh = $subscription->enhancements()->first();
        if (!$enh) {
            return response()->json(['success' => false, 'message' => 'Subscription enhancement not found'], 404);
        }

        $enh->grace_period_expires_at = now()->addDays($validated['days']);
        $enh->grace_period_status = 'active';
        $enh->grace_period_reason = $validated['note'] ?? null;
        $enh->save();

        return response()->json(['success' => true, 'message' => 'Grace period extended']);
    }
}
