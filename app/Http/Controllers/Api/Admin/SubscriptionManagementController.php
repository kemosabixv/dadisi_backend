<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Subscription Management Controller
 *
 * Handles administrative operations for managing subscribers and their plans.
 *
 * @group Admin - Subscription Management
 * @authenticated
 */
class SubscriptionManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * List all subscriptions with subscriber and plan details
     *
     * @authenticated
     * @queryParam search string Search by subscriber name, email, or plan name.
     * @queryParam status string Filter by status (active, expired, cancelled).
     * @queryParam plan_id integer Filter by plan ID.
     * @queryParam per_page integer Items per page. Default: 15.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('view_subscriptions'), 403, 'Unauthorized');

        try {
            // We want unique subscribers who have at least one subscription
            $query = \App\Models\User::query()
                ->whereHas('subscriptions')
                ->with([
                    'memberProfile',
                    'activeSubscription.plan',
                    'subscriptions' => function($q) {
                        $q->latest('starts_at')->with('plan');
                    }
                ]);

            // Search by User Name or Email
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('memberProfile', function ($pq) use ($search) {
                            $pq->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            }

            // Filter by Plan ID (Active or Latest)
            if ($request->filled('plan_id')) {
                $planId = $request->input('plan_id');
                $query->whereHas('subscriptions', function($q) use ($planId) {
                    $q->where('plan_id', $planId);
                });
            }

            // Filter by Status (Active or Latest)
            if ($request->filled('status')) {
                $status = $request->input('status');
                $query->whereHas('subscriptions', function($q) use ($status) {
                    if ($status === 'active') {
                        $q->where(function($sq) {
                            $sq->whereNull('ends_at')->orWhere('ends_at', '>', now());
                        })->whereNull('canceled_at');
                    } elseif ($status === 'expired') {
                        $q->where('ends_at', '<=', now());
                    } elseif ($status === 'cancelled') {
                        $q->whereNotNull('canceled_at');
                    }
                });
            }

            $paginated = $query->latest()->paginate($request->get('per_page', 15));
            
            $data = collect($paginated->items())->map(function($user) {
                // Get the latest subscription from the sorted collection
                // This is safer than activeSubscription->first() because subscriptions is already latest()
                $latestSub = $user->subscriptions->first();

                // Determine Plan Name
                $planDisplayName = 'N/A';
                if ($latestSub) {
                    $planDisplayName = $latestSub->plan?->name ?? 'Subscription';
                }

                return [
                    'id' => $latestSub?->id, // Keep ID for compatibility if needed, but we focus on user_id
                    'user_id' => $user->id,
                    'user_name' => $user->memberProfile?->full_name ?? $user->username ?? 'Unknown',
                    'user_email' => $user->email,
                    'plan_id' => $latestSub?->plan_id,
                    'plan_name' => $latestSub?->plan?->name,
                    'plan_display_name' => $planDisplayName,
                    'plan_price' => (float)($latestSub?->plan?->price ?? 0),
                    'status' => $latestSub?->status ?? 'none',
                    'starts_at' => $latestSub?->starts_at,
                    'ends_at' => $latestSub?->ends_at,
                    'expires_at' => $latestSub?->expires_at,
                    'canceled_at' => $latestSub?->canceled_at,
                    'auto_renew' => (bool)$user->renewalPreferences?->renewal_type === 'automatic',
                    'enhancements_count' => $latestSub ? $latestSub->enhancements->count() : 0,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Admin Subscription List Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscribers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single subscription details
     *
     * @authenticated
     */
    public function show(PlanSubscription $subscription): JsonResponse
    {
        abort_unless(auth()->user()->can('view_subscriptions'), 403, 'Unauthorized');

        // Load the specific subscription with its direct relations
        $subscription->load([
            'plan',
            'enhancements',
            'subscriber.memberProfile',
            'payments' => function($q) { $q->latest(); },
            'auditLogs' => function($q) { $q->latest(); }
        ]);

        // Also load the subscriber's full subscription history
        $subscriber = $subscription->subscriber;
        if ($subscriber) {
            $subscriber->load([
                'subscriptions' => function($q) {
                    $q->latest('starts_at')->with(['plan', 'payments', 'auditLogs']);
                }
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'current' => $subscription,
                'history' => $subscriber ? $subscriber->subscriptions : [],
                'subscriber' => $subscriber
            ]
        ]);
    }

    /**
     * Terminate a subscription immediately
     *
     * @authenticated
     */
    public function cancel(PlanSubscription $subscription): JsonResponse
    {
        abort_unless(auth()->user()->can('manage_subscriptions'), 403, 'Unauthorized');

        try {
            // Cancel immediately
            $subscription->cancel(true);
            
            \App\Models\AuditLog::log(
                'subscription.cancelled', 
                $subscription, 
                ['status' => 'active'], 
                ['status' => 'cancelled'], 
                'Cancelled by administrator'
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Admin Subscription Cancellation Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
