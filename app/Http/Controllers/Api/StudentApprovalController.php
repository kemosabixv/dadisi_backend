<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StudentApprovalRequest;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Student Approval Controller
 *
 * Manages student subscription approval requests and workflow
 * including submission, status checking, and admin operations (Phase 1)
 */
class StudentApprovalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Submit student plan approval request
     *
     * @group Subscriptions - Student Approvals
     * @authenticated
     * @description Submit a request for student subscription plan approval with documentation
     *
     * @bodyParam student_institution string required The student's educational institution. Example: University of Nairobi
     * @bodyParam student_email string required University email address for verification. Example: student@uon.ac.ke
     * @bodyParam documentation_url string required URL to student ID/verification document. Example: https://example.com/doc.pdf
     * @bodyParam birth_date date required Date of birth for age verification. Example: 2005-01-15
     * @bodyParam county string required County of residence. Example: Nairobi
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Student approval request submitted successfully",
     *   "data": {"id": 1, "status": "pending", "created_at": "2025-12-06T10:30:00Z"}
     * }
     */
    public function submitApprovalRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_institution' => 'required|string|max:255',
            'student_email' => 'required|email|max:255',
            'documentation_url' => 'required|url|max:500',
            'birth_date' => 'required|date|before:' . now()->subYears(16)->toDateString(),
            'county' => 'required|string|max:50',
            'additional_notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();

        try {
            DB::beginTransaction();

            // Check if user already has a pending/approved student request
            $existingRequest = StudentApprovalRequest::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active student approval request',
                    'data' => ['existing_request_id' => $existingRequest->id],
                ], 409);
            }

            // Create approval request
            $approvalRequest = StudentApprovalRequest::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'student_institution' => $validated['student_institution'],
                'student_email' => $validated['student_email'],
                'documentation_url' => $validated['documentation_url'],
                'student_birth_date' => $validated['birth_date'],
                'county' => $validated['county'],
                'additional_notes' => $validated['additional_notes'] ?? null,
                'submitted_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);

            DB::commit();

            Log::info('Student approval request submitted', [
                'user_id' => $user->id,
                'request_id' => $approvalRequest->id,
                'institution' => $validated['student_institution'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Student approval request submitted successfully',
                'data' => [
                    'id' => $approvalRequest->id,
                    'status' => $approvalRequest->status,
                    'created_at' => $approvalRequest->created_at,
                    'expires_at' => $approvalRequest->expires_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Student approval request submission failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit approval request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get student approval request status
     *
     * @group Subscriptions - Student Approvals
     * @authenticated
     * @description Get the current status of the authenticated user's student approval request
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "status": "pending",
     *     "student_institution": "University of Nairobi",
     *     "submitted_at": "2025-12-06T10:30:00Z"
     *   }
     * }
     */
    public function getApprovalStatus(): JsonResponse
    {
        $user = auth()->user();
        $approvalRequest = $user->studentApprovalRequests()
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$approvalRequest) {
            return response()->json([
                'success' => false,
                'message' => 'No approval request found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $approvalRequest->id,
                'status' => $approvalRequest->status,
                'student_institution' => $approvalRequest->student_institution,
                'submitted_at' => $approvalRequest->submitted_at,
                'reviewed_at' => $approvalRequest->reviewed_at,
                'expires_at' => $approvalRequest->expires_at,
                'rejection_reason' => $approvalRequest->rejection_reason,
                'admin_notes' => $approvalRequest->admin_notes,
            ],
        ]);
    }

    /**
     * Get approval request details (for admin review)
     *
     * @group Subscriptions - Student Approvals
     * @authenticated
     * @description Retrieve full details of a student approval request
     *
     * @urlParam request_id integer required The approval request ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {"id": 1, "user_id": 1, "status": "pending", "documentation_url": "https://..."}
     * }
     */
    public function getApprovalDetails($requestId): JsonResponse
    {
        $approvalRequest = StudentApprovalRequest::with('user')->findOrFail($requestId);

        // Check authorization - user can see their own, admins can see all
        if (auth()->user()->id !== $approvalRequest->user_id &&
            !auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $approvalRequest->id,
                'user_id' => $approvalRequest->user_id,
                'user_name' => $approvalRequest->user->name,
                'user_email' => $approvalRequest->user->email,
                'status' => $approvalRequest->status,
                'student_institution' => $approvalRequest->student_institution,
                'student_email' => $approvalRequest->student_email,
                'student_birth_date' => $approvalRequest->student_birth_date,
                'county' => $approvalRequest->county,
                'documentation_url' => $approvalRequest->documentation_url,
                'additional_notes' => $approvalRequest->additional_notes,
                'submitted_at' => $approvalRequest->submitted_at,
                'reviewed_at' => $approvalRequest->reviewed_at,
                'reviewed_by' => $approvalRequest->reviewed_by,
                'rejection_reason' => $approvalRequest->rejection_reason,
                'admin_notes' => $approvalRequest->admin_notes,
                'expires_at' => $approvalRequest->expires_at,
            ],
        ]);
    }

    /**
     * List pending approval requests (admin only)
     *
     * @group Subscriptions - Student Approvals
     * @authenticated
     * @description List all pending student approval requests for admin review
     *
     * @queryParam status string Filter by status. Example: pending
     * @queryParam county string Filter by county. Example: Nairobi
     * @queryParam per_page integer Results per page. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {"id": 1, "status": "pending", "user_name": "John Doe", "submitted_at": "2025-12-06"}
     *   ],
     *   "pagination": {"total": 1, "per_page": 15, "current_page": 1}
     * }
     */
    public function listApprovalRequests(Request $request): JsonResponse
    {
        // Check admin authorization
        if (!auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - admin only',
            ], 403);
        }

        $query = StudentApprovalRequest::with('user');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by county
        if ($request->has('county')) {
            $query->where('county', $request->county);
        }

        // Default: show pending if no filter
        if (!$request->has('status')) {
            $query->where('status', 'pending');
        }

        $perPage = $request->input('per_page', 15);
        $paginated = $query->orderBy('submitted_at', 'asc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'pagination' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * Approve student request (admin only)
     *
     * @group Subscriptions - Student Approvals
     * @authenticated
     * @description Approve a student's plan subscription request and create student subscription
     *
     * @urlParam request_id integer required The approval request ID. Example: 1
     * @bodyParam admin_notes string Admin review notes. Example: Documents verified successfully
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Student request approved successfully",
     *   "data": {"status": "approved", "subscription_id": 1}
     * }
     */
    public function approveRequest($requestId, Request $request): JsonResponse
    {
        // Check admin authorization
        if (!auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - admin only',
            ], 403);
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $approvalRequest = StudentApprovalRequest::findOrFail($requestId);

        if ($approvalRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Request is not in pending status',
            ], 409);
        }

        try {
            DB::beginTransaction();

            // Approve the request
            $approvalRequest->approve(auth()->user()->id, $validated['admin_notes'] ?? null);

            // Create student subscription
            $studentPlan = Plan::where('type', 'student')->first();
            if ($studentPlan) {
                $approvalRequest->user->activeSubscription()->create([
                    'plan_id' => $studentPlan->id,
                    'starts_at' => now(),
                    'ends_at' => now()->addYear(),
                    'status' => 'active',
                ]);

                // Create subscription enhancement
                $subscription = $approvalRequest->user->activeSubscription()->latest()->first();
                if ($subscription) {
                    SubscriptionEnhancement::create([
                        'subscription_id' => $subscription->id,
                        'status' => 'active',
                        'max_renewal_attempts' => 3,
                        'metadata' => json_encode(['student_plan' => true]),
                    ]);
                }
            }

            DB::commit();

            Log::info('Student approval request approved', [
                'admin_id' => auth()->user()->id,
                'request_id' => $requestId,
                'user_id' => $approvalRequest->user_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Student request approved successfully',
                'data' => [
                    'status' => $approvalRequest->status,
                    'approved_at' => $approvalRequest->reviewed_at,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Student approval request approval failed', [
                'admin_id' => auth()->user()->id,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject student request (admin only)
     *
     * @group Subscriptions - Student Approvals
     * @authenticated
     * @description Reject a student's plan subscription request
     *
     * @urlParam request_id integer required The approval request ID. Example: 1
     * @bodyParam rejection_reason string required Reason for rejection. Example: Documentation incomplete
     * @bodyParam admin_notes string Admin review notes. Example: Student provided wrong ID type
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Student request rejected",
     *   "data": {"status": "rejected"}
     * }
     */
    public function rejectRequest($requestId, Request $request): JsonResponse
    {
        // Check admin authorization
        if (!auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - admin only',
            ], 403);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:255',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $approvalRequest = StudentApprovalRequest::findOrFail($requestId);

        if ($approvalRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Request is not in pending status',
            ], 409);
        }

        try {
            DB::beginTransaction();

            // Reject the request
            $approvalRequest->reject(
                auth()->user()->id,
                $validated['rejection_reason'],
                $validated['admin_notes'] ?? null
            );

            DB::commit();

            Log::info('Student approval request rejected', [
                'admin_id' => auth()->user()->id,
                'request_id' => $requestId,
                'user_id' => $approvalRequest->user_id,
                'reason' => $validated['rejection_reason'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Student request rejected',
                'data' => [
                    'status' => $approvalRequest->status,
                    'rejection_reason' => $approvalRequest->rejection_reason,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Student approval request rejection failed', [
                'admin_id' => auth()->user()->id,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if user can request student plan
     *
     * @group Subscriptions - Student Approvals
     * @authenticated
     * @description Check user eligibility for student plan subscription
     *
     * @response 200 {
     *   "success": true,
     *   "can_request": true,
     *   "reason": "You are eligible to request student plan"
     * }
     */
    public function canRequestStudentPlan(): JsonResponse
    {
        $user = auth()->user();

        // Check for existing pending/approved requests
        $existingRequest = StudentApprovalRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existingRequest) {
            return response()->json([
                'success' => true,
                'can_request' => false,
                'reason' => 'You already have an active student plan request',
                'existing_request' => $existingRequest,
            ]);
        }

        // Check for existing student subscription
        $subscription = $user->activeSubscription()
            ->whereHas('plan', function ($q) {
                $q->where('type', 'student');
            })
            ->first();

        if ($subscription) {
            return response()->json([
                'success' => true,
                'can_request' => false,
                'reason' => 'You already have an active student subscription',
            ]);
        }

        return response()->json([
            'success' => true,
            'can_request' => true,
            'reason' => 'You are eligible to request student plan',
        ]);
    }
}
