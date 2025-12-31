<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Refund;
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
        $this->middleware(['auth:sanctum', 'admin']);
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
        try {
            $refund->load(['payment', 'processor', 'refundable']);

            return response()->json([
                'success' => true,
                'data' => $refund,
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
    public function approve(Request $request, Refund $refund): JsonResponse
    {
        try {
            $approvedRefund = $this->refundService->approveRefund(
                $refund, 
                auth()->user(), 
                $request->input('admin_notes')
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
    public function reject(Request $request, Refund $refund): JsonResponse
    {
        try {
            $validated = $request->validate([
                'admin_notes' => 'required|string|max:1000',
            ]);

            $rejectedRefund = $this->refundService->rejectRefund(
                $refund, 
                auth()->user(), 
                $validated['admin_notes']
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
}
