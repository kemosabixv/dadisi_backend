<?php

namespace App\Http\Controllers\Api\Admin;

use App\DTOs\ApproveRefundDTO;
use App\DTOs\RejectRefundDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveRefundRequest;
use App\Http\Requests\RejectRefundRequest;
use App\Models\Refund;
use App\Models\Payment;
use App\Services\Contracts\RefundServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Refund Management Controller
 *
 * Handles administrative refund operations including listing, approval, and processing.
 *
 * @group Admin - Refund Management
 * @groupDescription Administrative endpoints for managing refund requests and processing.
 */
class RefundController extends Controller
{
    public function __construct(
        private RefundServiceContract $refundService
    ) {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * List all refunds
     *
     * Retrieves a paginated list of all refund requests with optional filters.
     *
     * @authenticated
     * @queryParam status string Filter by refund status. Example: pending
     * @queryParam reason string Filter by refund reason. Example: cancellation
     * @queryParam search string Search by transaction reference.
     * @queryParam per_page integer Items per page. Default: 20. Example: 20
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "refundable_type": "App\\Models\\EventOrder",
     *       "refundable_id": 123,
     *       "amount": 500.00,
     *       "status": "pending",
     *       "reason": "cancellation"
     *     }
     *   ],
     *   "meta": {"total": 50, "per_page": 20, "current_page": 1}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage_refunds');

        try {
            $filters = [
                'status' => $request->input('status'),
                'reason' => $request->input('reason'),
                'search' => $request->input('search'),
            ];

            $refunds = $this->refundService->listRefunds($filters, $request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $refunds->items(),
                'meta' => [
                    'current_page' => $refunds->currentPage(),
                    'last_page' => $refunds->lastPage(),
                    'total' => $refunds->total(),
                    'per_page' => $refunds->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list refunds', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve refunds'], 500);
        }
    }

    /**
     * Get refund details
     *
     * Retrieves detailed information about a specific refund request.
     *
     * @authenticated
     * @urlParam refund required The refund ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "amount": 500.00,
     *     "status": "pending",
     *     "reason": "cancellation",
     *     "customer_notes": "Event was cancelled",
     *     "payment": {...},
     *     "processor": {...}
     *   }
     * }
     */
    public function show(Refund $refund): JsonResponse
    {
        $this->authorize('manage_refunds');

        try {
            $refund->load(['payment', 'processor', 'refundable.user']);

            // Pick essential fields to satisfy the AdminRefundSchema while avoiding relationship overexposure
            $data = [
                'id' => $refund->id,
                'refundable_type' => $refund->refundable_type,
                'refundable_id' => $refund->refundable_id,
                'payment_id' => $refund->payment_id,
                'processed_by' => $refund->processed_by,
                'amount' => $refund->amount,
                'currency' => $refund->currency,
                'original_amount' => $refund->original_amount ?? $refund->amount,
                'status' => $refund->status,
                'reason' => $refund->reason,
                'customer_notes' => $refund->customer_notes,
                'admin_notes' => $refund->admin_notes,
                'gateway' => $refund->gateway,
                'requested_at' => $refund->requested_at->toIso8601String(),
                'approved_at' => $refund->approved_at?->toIso8601String(),
                'processed_at' => $refund->processed_at?->toIso8601String(),
                'completed_at' => $refund->completed_at?->toIso8601String(),
                'created_at' => $refund->created_at->toIso8601String(),
                'updated_at' => $refund->updated_at->toIso8601String(),
                'metadata' => $refund->metadata,
                'payment' => $refund->payment ? [
                    'id' => $refund->payment->id,
                    'method' => $refund->payment->method ?? $refund->payment->payment_method,
                    'transaction_id' => $refund->payment->transaction_id,
                    'confirmation_code' => $refund->payment->confirmation_code,
                    'external_reference' => $refund->payment->external_reference,
                    'amount' => $refund->payment->amount,
                    'currency' => $refund->payment->currency,
                    'paid_at' => $refund->payment->paid_at?->toIso8601String(),
                ] : null,
                'processor' => $refund->processor ? [
                    'id' => $refund->processor->id,
                    'username' => $refund->processor->username,
                ] : null,
            ];

            // Append requester info from the refundable (EventOrder, Donation, etc.)
            $refundable = $refund->refundable;
            if ($refundable) {
                $data['requester'] = [
                    'name' => $refundable->attendee_name ?? $refundable->guest_name ?? ($refundable->user?->display_name ?? $refundable->user?->username ?? 'Unknown'),
                    'email' => $refundable->attendee_email ?? $refundable->guest_email ?? ($refundable->user?->email ?? null),
                    'phone' => $refundable->guest_phone ?? ($refundable->user?->phone_number ?? null),
                    'user_id' => $refundable->user_id ?? null,
                    'is_guest' => ($refundable->user_id === null),
                ];

                // Append specific details based on type
                if ($refundable instanceof \App\Models\LabBooking) {
                    $data['refundable_details'] = [
                        'space_name' => $refundable->labSpace?->name,
                        'starts_at' => $refundable->starts_at->toIso8601String(),
                        'ends_at' => $refundable->ends_at->toIso8601String(),
                        'booking_reference' => $refundable->booking_reference,
                        'series_id' => $refundable->booking_series_id,
                    ];
                } elseif ($refundable instanceof \App\Models\EventOrder) {
                    $data['refundable_details'] = [
                        'event_title' => $refundable->event?->title,
                        'order_number' => $refundable->order_number,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get refund details', ['error' => $e->getMessage(), 'refund_id' => $refund->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve refund'], 500);
        }
    }

    /**
     * Approve a refund
     *
     * Approves a pending refund request.
     *
     * @authenticated
     * @urlParam refund required The refund ID. Example: 1
     * @bodyParam admin_notes string optional Internal notes about the approval. Example: Verified cancellation
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Refund approved successfully",
     *   "data": {...}
     * }
     */
    public function approve(ApproveRefundRequest $request, Refund $refund): JsonResponse
    {
        try {
            $dto = ApproveRefundDTO::fromArray($request->validated());
            $approvedRefund = $this->refundService->approveRefund(
                $refund, 
                auth()->user(), 
                $dto->admin_notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Refund approved successfully.',
                'data' => $approvedRefund,
            ]);
        } catch (\Exception $e) {
            Log::error('Refund approval failed', ['error' => $e->getMessage(), 'refund_id' => $refund->id]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reject a refund
     *
     * Rejects a pending refund request.
     *
     * @authenticated
     * @urlParam refund required The refund ID. Example: 1
     * @bodyParam admin_notes string required Reason for rejection. Example: Refund period has expired
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Refund rejected",
     *   "data": {...}
     * }
     */
    public function reject(RejectRefundRequest $request, Refund $refund): JsonResponse
    {
        try {
            $dto = RejectRefundDTO::fromArray($request->validated());
            $rejectedRefund = $this->refundService->rejectRefund(
                $refund, 
                auth()->user(), 
                $dto->admin_notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Refund rejected.',
                'data' => $rejectedRefund,
            ]);
        } catch (\Exception $e) {
            Log::error('Refund rejection failed', ['error' => $e->getMessage(), 'refund_id' => $refund->id]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Process an approved refund
     *
     * Initiates the refund processing through the payment gateway.
     *
     * @authenticated
     * @urlParam refund required The refund ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Refund processed successfully",
     *   "data": {...}
     * }
     */
    public function process(Refund $refund): JsonResponse
    {
        $this->authorize('manage_refunds');

        try {
            $processedRefund = $this->refundService->processRefund($refund);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully.',
                'data' => $processedRefund,
            ]);
        } catch (\Exception $e) {
            Log::error('Refund processing failed', ['error' => $e->getMessage(), 'refund_id' => $refund->id]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get refund statistics
     *
     * Retrieves summary statistics for refund management.
     *
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "pending": 5,
     *     "approved": 2,
     *     "completed": 50,
     *     "total_refunded": 25000.00
     *   }
     * }
     */
    public function stats(): JsonResponse
    {
        $this->authorize('manage_refunds');

        try {
            $stats = $this->refundService->getStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get refund stats', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve statistics'], 500);
        }
    }

    /**
     * Initiate a new refund request (Admin only)
     *
     * @authenticated
     * @bodyParam payment_reference string required The payment reference (transaction ID or external reference).
     * @bodyParam reason string required The reason for the refund.
     * @bodyParam amount float optional Custom refund amount.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage_refunds');

        try {
            $validated = $request->validate([
                'payment_reference' => 'required|string',
                'reason' => 'required|string|max:500',
                'amount' => 'nullable|numeric|min:0.01',
                'customer_notes' => 'nullable|string|max:1000',
            ]);

            // 1. Find the payment
            $payment = Payment::where('external_reference', $validated['payment_reference'])
                ->orWhere('transaction_id', $validated['payment_reference'])
                ->first();

            if (!$payment) {
                return response()->json(['success' => false, 'message' => 'Payment not found.'], 404);
            }

            if (!$payment->isPaid()) {
                return response()->json(['success' => false, 'message' => 'Only paid payments can be refunded.'], 400);
            }

            if (!$payment->payable_type || !$payment->payable_id) {
                return response()->json(['success' => false, 'message' => 'Payment is not associated with a refundable entity.'], 400);
            }

            // 2. Submit the refund request
            $refund = $this->refundService->submitRefundRequest(
                $payment->payable_type,
                $payment->payable_id,
                $validated['reason'],
                $validated['customer_notes'] ?? "Requested by admin: " . auth()->user()->username
            );

            return response()->json([
                'success' => true,
                'message' => 'Refund request initiated successfully.',
                'data' => $refund,
            ]);

        } catch (\Exception $e) {
            Log::error('Admin refund initiation failed', [
                'error' => $e->getMessage(),
                'user' => auth()->id(),
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
