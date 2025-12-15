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
     * @group Subscriptions - Renewals
     * @authenticated
     * @urlParam id integer required The subscription id. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Manual renewal requested. Reminders scheduled."
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
        * Get renewal payment options for a subscription (placeholder)
        *
        * @group Subscriptions - Renewals
        * @authenticated
        * @urlParam id integer required The subscription id. Example: 1
        *
        * @response 200 {
        *   "success": true,
        *   "data": {"payment_methods": [{"type":"mobile_money","display":"M-Pesa"}], "amount": 99.99, "currency": "KES"}
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
        * Confirm manual renewal (placeholder) — this would create a renewal job or trigger payment flow
        *
        * @group Subscriptions - Renewals
        * @authenticated
        * @urlParam id integer required The subscription id. Example: 1
        * @bodyParam payment_method string required The payment method chosen. Example: mobile_money
        *
        * @response 200 {"success": true, "message": "Renewal confirmed (mock)"}
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
        * List pending reminders for the authenticated user
        *
        * @group Subscriptions - Renewals
        * @authenticated
        * @response 200 {"success": true, "data": []}
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
        * @group Subscriptions - Renewals (Admin)
        * @authenticated
        * @bodyParam days integer required Number of days to extend. Example: 14
        * @bodyParam note string optional Reason for extension
        * @response 200 {"success": true, "message": "Grace period extended"}
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
