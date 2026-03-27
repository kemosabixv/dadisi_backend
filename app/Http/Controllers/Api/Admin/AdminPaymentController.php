<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Contracts\PaymentServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * AdminPaymentController
 *
 * Handles administrative payment operations including listing, viewing, and refunding.
 *
 * @group Admin - Finance Management
 */
class AdminPaymentController extends Controller
{
    public function __construct(
        protected PaymentServiceContract $paymentService
    ) {
        $this->middleware(['auth', 'verified']);
    }

    /**
     * List all payments with advanced filtering and search
     *
     * @authenticated
     *
     * @queryParam status string Filter by status (paid, pending, refunded, failed, canceled).
     * @queryParam type string Filter by payable type (e.g., event_order, donation, subscription).
     * @queryParam search string Search by payer name, email, or reference.
     * @queryParam date_from string Filter by start date (YYYY-MM-DD).
     * @queryParam date_to string Filter by end date (YYYY-MM-DD).
     * @queryParam per_page integer Items per page. Default: 15.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('manage_payments'), 403, 'Unauthorized');

        try {
            // Valid payable types (morph names or class names)
            $validPayableTypes = [
                'event_order',
                'donation',
                'subscription',
                'App\\Models\\EventOrder',
                'App\\Models\\Donation',
                'App\\Models\\PlanSubscription',
                'TestPayment',
                'App\\Models\\TestPayment',
                'test',
            ];

            // Build query - only eager load payer (safe for all payment types)
            $query = Payment::query()
                ->whereIn('payable_type', $validPayableTypes)
                ->with(['payer']);

            // Search by Payer or Reference
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhere('external_reference', 'like', "%{$search}%")
                        ->orWhere('transaction_id', 'like', "%{$search}%")
                        ->orWhereHas('payer', function ($pq) use ($search) {
                            $pq->where('email', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhereHas('memberProfile', function ($mq) use ($search) {
                                    $mq->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%");
                                });
                        });
                });
            }

            // Filter by Status
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by Type (Payable Type)
            if ($request->filled('type')) {
                $type = $request->input('type');
                // Map short names to potential legacy class names for broad search
                $typeMap = [
                    'event' => ['event_order', 'App\\Models\\EventOrder'],
                    'event_order' => ['event_order', 'App\\Models\\EventOrder'],
                    'donation' => ['donation', 'App\\Models\\Donation'],
                    'subscription' => ['subscription', 'App\\Models\\PlanSubscription'],
                ];

                $searchTypes = $typeMap[$type] ?? [$type];
                $query->whereIn('payable_type', $searchTypes);
            }

            // Filter by Date
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }

            $paginated = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            // Eagerly resolve "Guest" payers that are actually registered users
            $items = $paginated->items();
            $guestEmails = [];
            $orderPayableIds = [];

            foreach ($items as $payment) {
                if (! $payment->payer_id) {
                    if (isset($payment->meta['user_email'])) {
                        $guestEmails[] = $payment->meta['user_email'];
                    }
                    if ($payment->payable_type === 'event_order' || $payment->payable_type === 'App\\Models\\EventOrder') {
                        $orderPayableIds[] = $payment->payable_id;
                    }
                }
            }

            // 1. Resolve by Email (Existing Meta Logic)
            $usersByEmail = collect();
            if (! empty($guestEmails)) {
                $usersByEmail = \App\Models\User::whereIn('email', array_unique($guestEmails))
                    ->with('memberProfile')
                    ->get()
                    ->keyBy('email');
            }

            // 2. Resolve by linked EventOrder user_id (New Fallback Logic)
            $orderUserIdMap = collect();
            $usersById = collect();
            if (! empty($orderPayableIds)) {
                $orderUserIdMap = \App\Models\EventOrder::whereIn('id', array_unique($orderPayableIds))
                    ->whereNotNull('user_id')
                    ->get(['id', 'user_id'])
                    ->pluck('user_id', 'id');

                if ($orderUserIdMap->isNotEmpty()) {
                    $usersById = \App\Models\User::whereIn('id', $orderUserIdMap->values()->unique())
                        ->with('memberProfile')
                        ->get()
                        ->keyBy('id');
                }
            }

            // Apply relations
            foreach ($items as $payment) {
                if ($payment->payer_id) continue;

                // Priority: Direct ID link from EventOrder
                if ($payment->payable_type === 'event_order' || $payment->payable_type === 'App\\Models\\EventOrder') {
                    $userId = $orderUserIdMap->get($payment->payable_id);
                    if ($userId && $usersById->has($userId)) {
                        $payment->setRelation('payer', $usersById->get($userId));
                        continue;
                    }
                }

                // Fallback: Email-based resolution
                if (isset($payment->meta['user_email'])) {
                    $user = $usersByEmail->get($payment->meta['user_email']);
                    if ($user) {
                        $payment->setRelation('payer', $user);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $items,
                'meta' => [
                    'last_page' => $paginated->lastPage(),
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Admin Payment List Error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single payment details
     *
     * @authenticated
     */
    public function show(Payment $payment): JsonResponse
    {
        abort_unless(auth()->user()->can('manage_payments'), 403, 'Unauthorized');

        // Conditionally load payable only if it's a real model
        $isTest = $payment->payable_type === 'test' ||
                  $payment->payable_type === 'TestPayment' ||
                  $payment->payable_type === 'App\\Models\\TestPayment' ||
                  ($payment->meta['test_payment'] ?? false);

        if ($isTest) {
            $payment->load(['payer']);
        } else {
            $relations = ['payer', 'payable'];
            
            // Only attempt to load promoCode if it's an EventOrder
            if ($payment->payable_type === 'event_order' || $payment->payable_type === 'App\\Models\\EventOrder') {
                $relations[] = 'payable.promoCode';
            }
            
            $payment->load($relations);
        }

        // Resolve "Guest" payer if it's actually a registered user
        if (! $payment->payer) {
            $user = null;

            // 1. Try resolving by EventOrder user_id (New Fallback Logic)
            if ($payment->payable_type === 'event_order' && $payment->payable) {
                $userId = $payment->payable->user_id;
                if ($userId) {
                    $user = \App\Models\User::where('id', $userId)->with('memberProfile')->first();
                }
            }

            // 2. Try resolving by Email (Existing Meta Logic)
            if (! $user && isset($payment->meta['user_email'])) {
                $user = \App\Models\User::where('email', $payment->meta['user_email'])
                    ->with('memberProfile')
                    ->first();
            }

            if ($user) {
                $payment->setRelation('payer', $user);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $payment,
        ]);
    }

    /**
     * Process a refund for a payment
     *
     * @authenticated
     */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        abort_unless(auth()->user()->can('refund_payments'), 403, 'Unauthorized');

        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        try {
            $result = $this->paymentService->refundPayment(auth()->user(), [
                'transaction_id' => $payment->transaction_id ?? $payment->external_reference ?? $payment->id,
                'reason' => $request->input('reason'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Admin Refund Error', ['error' => $e->getMessage(), 'payment_id' => $payment->id]);

            return response()->json([
                'success' => false,
                'message' => 'Refund failed: '.$e->getMessage(),
            ], 400);
        }
    }
}
