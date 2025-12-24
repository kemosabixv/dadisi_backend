<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Services\RefundService;
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
        protected RefundService $refundService
    ) {}

    /**
     * List all refunds
     *
     * Retrieves a paginated list of all refund requests with optional filters.
     *
     * @authenticated
     * @queryParam status string Filter by refund status. Example: pending
     * @queryParam reason string Filter by refund reason. Example: cancellation
     * @queryParam per_page integer Items per page. Default: 20. Example: 20
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "data": [
     *       {
     *         "id": 1,
     *         "refundable_type": "App\\Models\\EventOrder",
     *         "refundable_id": 123,
     *         "amount": 500.00,
     *         "status": "pending",
     *         "reason": "cancellation"
     *       }
     *     ],
     *     "total": 50,
     *     "per_page": 20
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $query = Refund::with(['payment', 'processor'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->reason, fn($q) => $q->where('reason', $request->reason))
            ->when($request->search, function ($q) use ($request) {
                $q->whereHas('payment', function ($pq) use ($request) {
                    $pq->where('external_reference', 'like', "%{$request->search}%");
                });
            })
            ->orderBy('created_at', 'desc');

        $refunds = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $refunds,
        ]);
    }

    /**
     * Get refund details
     *
     * Retrieves detailed information about a specific refund request.
     *
     * @authenticated
     * @urlParam id integer required The refund ID. Example: 1
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
        $refund->load(['payment', 'processor', 'refundable']);

        return response()->json([
            'success' => true,
            'data' => $refund,
        ]);
    }

    /**
     * Approve a refund
     *
     * Approves a pending refund request.
     *
     * @authenticated
     * @urlParam id integer required The refund ID. Example: 1
     * @bodyParam admin_notes string optional Internal notes about the approval. Example: Verified cancellation
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Refund approved successfully",
     *   "data": {...}
     * }
     * @response 400 {
     *   "success": false,
     *   "message": "Refund cannot be approved in current status"
     * }
     */
    public function approve(Request $request, Refund $refund): JsonResponse
    {
        if (!$refund->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending refunds can be approved.',
            ], 400);
        }

        $refund->approve(auth()->user());

        if ($request->admin_notes) {
            $refund->update(['admin_notes' => $request->admin_notes]);
        }

        Log::info('Refund approved', [
            'refund_id' => $refund->id,
            'approved_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Refund approved successfully.',
            'data' => $refund->fresh(),
        ]);
    }

    /**
     * Reject a refund
     *
     * Rejects a pending refund request.
     *
     * @authenticated
     * @urlParam id integer required The refund ID. Example: 1
     * @bodyParam admin_notes string required Reason for rejection. Example: Refund period has expired
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Refund rejected",
     *   "data": {...}
     * }
     */
    public function reject(Request $request, Refund $refund): JsonResponse
    {
        $validated = $request->validate([
            'admin_notes' => 'required|string|max:1000',
        ]);

        if (!$refund->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending refunds can be rejected.',
            ], 400);
        }

        $refund->reject(auth()->user(), $validated['admin_notes']);

        Log::info('Refund rejected', [
            'refund_id' => $refund->id,
            'rejected_by' => auth()->id(),
            'reason' => $validated['admin_notes'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Refund rejected.',
            'data' => $refund->fresh(),
        ]);
    }

    /**
     * Process an approved refund
     *
     * Initiates the refund processing through the payment gateway.
     *
     * @authenticated
     * @urlParam id integer required The refund ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Refund processed successfully",
     *   "data": {...}
     * }
     * @response 400 {
     *   "success": false,
     *   "message": "Refund cannot be processed"
     * }
     */
    public function process(Refund $refund): JsonResponse
    {
        try {
            $processedRefund = $this->refundService->processRefund($refund);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully.',
                'data' => $processedRefund,
            ]);

        } catch (\Exception $e) {
            Log::error('Refund processing failed', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);

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
        $stats = $this->refundService->getStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
