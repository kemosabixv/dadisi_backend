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
        $this->middleware(['auth:sanctum', 'verified']);
    }

    /**
     * List all payments with advanced filtering and search
     *
     * @authenticated
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
            $query = Payment::query()->with(['payer', 'payable']);

            // Search by Payer or Reference
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                      ->orWhere('external_reference', 'like', "%{$search}%")
                      ->orWhere('transaction_id', 'like', "%{$search}%")
                      ->orWhereHas('payer', function($pq) use ($search) {
                          $pq->where('name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%")
                             ->orWhere('username', 'like', "%{$search}%");
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
                // Map short names to full model names if needed
                $typeMap = [
                    'event' => 'App\\Models\\EventOrder',
                    'donation' => 'App\\Models\\Donation',
                    'subscription' => 'App\\Models\\PlanSubscription',
                ];
                $actualType = $typeMap[$type] ?? $type;
                $query->where('payable_type', 'like', "%{$actualType}%");
            }

            // Filter by Date
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }

            $payments = $query->orderBy('created_at', 'desc')
                              ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $payments->items(),
                'meta' => [
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'total' => $payments->total(),
                    'per_page' => $payments->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Admin Payment List Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payments',
                'error' => $e->getMessage()
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

        $payment->load(['payer', 'payable']);

        return response()->json([
            'success' => true,
            'data' => $payment
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
                'reason' => $request->input('reason')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Admin Refund Error', ['error' => $e->getMessage(), 'payment_id' => $payment->id]);
            return response()->json([
                'success' => false,
                'message' => 'Refund failed: ' . $e->getMessage()
            ], 400);
        }
    }
}
