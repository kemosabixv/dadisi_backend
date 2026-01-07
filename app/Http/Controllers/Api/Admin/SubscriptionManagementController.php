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
        $this->middleware(['auth:sanctum', 'admin']);
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
            $query = PlanSubscription::query()
                ->with('plan')
                ->with('enhancements')
                ->with('subscriber.memberProfile')
                ->latest('starts_at');

            // Search by User (subscriber) or Plan Name
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->whereHas('subscriber', function ($uq) use ($search) {
                        $uq->where('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhereHas('memberProfile', function ($pq) use ($search) {
                                $pq->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereHas('plan', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%");
                    });
                });
            }

            // Filter by Status
            if ($request->filled('status')) {
                $status = $request->input('status');
                if ($status === 'active') {
                    $query->where(function($q) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                    });
                } elseif ($status === 'expired') {
                    $query->where('ends_at', '<=', now());
                } elseif ($status === 'cancelled') {
                    $query->whereNotNull('canceled_at');
                }
            }

            // Filter by Plan
            if ($request->filled('plan_id')) {
                $query->where('plan_id', $request->input('plan_id'));
            }

            $paginated = $query->paginate($request->get('per_page', 15));
            
            $data = collect($paginated->items())->map(function($sub) {
                // Determine Plan Name
                $planDisplayName = 'Subscription';
                if (is_array($sub->name)) {
                    $planDisplayName = $sub->name['en'] ?? $sub->name['default'] ?? 'Subscription';
                } else if (is_string($sub->name)) {
                    $decoded = json_decode($sub->name, true);
                    $planDisplayName = $decoded['en'] ?? $decoded['default'] ?? $sub->name;
                }

                return [
                    'id' => $sub->id,
                    'user_id' => $sub->subscriber_id,
                    'user_name' => $sub->subscriber?->memberProfile?->full_name ?? $sub->subscriber?->username ?? 'Unknown',
                    'user_email' => $sub->subscriber?->email,
                    'plan_id' => $sub->plan_id,
                    'plan_name' => $sub->plan?->name,
                    'plan_display_name' => $planDisplayName,
                    'plan_price' => (float)($sub->plan?->price ?? 0),
                    'status' => $sub->status,
                    'starts_at' => $sub->starts_at,
                    'ends_at' => $sub->ends_at,
                    'expires_at' => $sub->expires_at,
                    'canceled_at' => $sub->canceled_at,
                    'auto_renew' => (bool)$sub->subscriber?->renewalPreferences?->renewal_type === 'automatic',
                    'enhancements_count' => $sub->enhancements->count(),
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
                'message' => 'Failed to retrieve subscriptions',
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

        $subscription->load([
            'plan',
            'enhancements',
            'subscriber.memberProfile',
            'payments' => function($q) { $q->latest(); },
            'auditLogs' => function($q) { $q->latest(); }
        ]);

        return response()->json([
            'success' => true,
            'data' => $subscription
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
