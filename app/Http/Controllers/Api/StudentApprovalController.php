<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentApprovalRequest;
use App\Services\Contracts\StudentApprovalServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Student Approval Controller
 *
 * Manages student subscription approval requests and workflow
 * including submission, status checking, and admin operations (Phase 1)
 */
class StudentApprovalController extends Controller
{
    public function __construct(
        private StudentApprovalServiceContract $studentApprovalService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Submit student plan approval request
     *
     * Allows an authenticated user to submit proof of student status (e.g., ID card, enrollment letter) to qualify for student pricing.
     * Requires a valid institution name, email, and link to verification documents.
     * Only one active request is allowed per user.
     *
     * @group Subscriptions - Student Approvals
     * @groupDescription Comprehensive workflow for verifying student status to grant access to discounted student plans. Includes endpoints for students to submit applications and for admins to review, approve, or reject them.
     * @authenticated
     *
     * @bodyParam student_institution string required The student's educational institution. Example: University of Nairobi
     * @bodyParam student_email string required University email address for verification. Example: student@uon.ac.ke
     * @bodyParam documentation_url string required URL to student ID/verification document. Example: https://example.com/doc.pdf
     * @bodyParam birth_date date optional Date of birth (manual verification by staff). Example: 2005-01-15
     * @bodyParam county string required County of residence. Example: Nairobi
     * @bodyParam additional_notes string optional Any extra details to support the application. Example: I am a first year student.
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Student approval request submitted successfully",
     *   "data": {
     *     "id": 1,
     *     "status": "pending",
     *     "created_at": "2025-12-06T10:30:00Z",
     *     "expires_at": "2026-01-05T10:30:00Z"
     *   }
     * }
     * @response 409 {
     *   "success": false,
     *   "message": "You already have an active student approval request"
     * }
     */
    public function submitApprovalRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_institution' => 'required|string|max:255',
            'student_email' => 'required|email|max:255',
            'documentation_url' => 'required|url|max:500',
            'birth_date' => 'nullable|date',
            'county' => 'required|string|max:50',
            'additional_notes' => 'nullable|string|max:500',
        ]);

        try {
            $approvalRequest = $this->studentApprovalService->submitApprovalRequest(
                $request->user()->id,
                $validated
            );

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
            Log::error('Student approval request submission failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => str_contains($e->getMessage(), 'already have') ? $e->getMessage() : 'Failed to submit approval request',
                'error' => $e->getMessage(),
            ], str_contains($e->getMessage(), 'already have') ? 409 : 500);
        }
    }

    /**
     * Get student approval request status
     *
     * Retrieves the most recent student approval request for the authenticated user.
     * Returns details including current status (pending, approved, rejected), submission date, and any admin feedback.
     *
     * @group Subscriptions - Student Approvals
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "status": "pending",
     *     "student_institution": "Kenyatta University",
     *     "submitted_at": "2025-12-06T10:30:00Z",
     *     "rejection_reason": null,
     *     "admin_notes": null
     *   }
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "No approval request found"
     * }
     */
    public function getApprovalStatus(Request $request): JsonResponse
    {
        try {
            $approvalRequest = $this->studentApprovalService->getApprovalStatus($request->user()->id);

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
        } catch (\Exception $e) {
            Log::error('Failed to retrieve approval status', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve approval status'], 500);
        }
    }

    /**
     * Get approval request details (for admin review)
     *
     * Fetches complete information about a specific student request, including personal details and verification documents.
     * Users can view their own requests; Admins can view any request.
     *
     * @group Subscriptions - Student Approvals
     * @authenticated
     *
     * @urlParam request_id integer required The unique ID of the approval request. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "user_name": "John Doe",
     *     "status": "pending",
     *     "documentation_url": "https://example.com/doc.pdf",
     *     "student_institution": "University of Nairobi"
     *   }
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "Unauthorized"
     * }
     */
    public function getApprovalDetails(Request $request, $requestId): JsonResponse
    {
        try {
            $approvalRequest = $this->studentApprovalService->getApprovalDetails($requestId);

            // Check authorization - user can see their own, admins can see all
            if ($request->user()->id !== $approvalRequest->user_id &&
                !$request->user()->can('view_student_approvals')) {
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
                    'user_name' => $approvalRequest->user ? $approvalRequest->user->display_name : 'Unknown User',
                    'user_email' => $approvalRequest->user ? $approvalRequest->user->email : 'N/A',
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
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Approval request not found', ['request_id' => $requestId]);
            return response()->json(['success' => false, 'message' => 'Approval request not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve approval details', [
                'error' => $e->getMessage(),
                'request_id' => $requestId,
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve approval details'], 500);
        }
    }

    /**
     * List pending approval requests (admin only)
     *
     * Retrieves a paginated list of student approval requests for administrative review.
     * Supports filtering by status (e.g., 'pending') and location (county) to help admins manage their review queue.
     *
     * @group Subscriptions - Student Approvals
     * @authenticated
     *
     * @queryParam status string Filter by status (pending, approved, rejected). Default: pending. Example: pending
     * @queryParam county string Filter by county. Example: Nairobi
     * @queryParam per_page integer Results per page. Default: 15. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "status": "pending",
     *       "user_name": "John Doe",
     *       "student_institution": "University of Nairobi",
     *       "submitted_at": "2025-12-06T10:30:00Z"
     *     }
     *   ],
     *   "pagination": {
     *     "total": 1,
     *     "per_page": 15,
     *     "current_page": 1,
     *     "last_page": 1
     *   }
     * }
     */
    public function listApprovalRequests(Request $request): JsonResponse
    {
        try {
            // Check admin authorization
            if (!$request->user()->can('view_student_approvals')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - inadequate permissions',
                ], 403);
            }

            $filters = [
                'county' => $request->input('county'),
                'per_page' => $request->input('per_page', 15),
            ];

            if ($request->has('status')) {
                $filters['status'] = $request->input('status');
            }

            $paginated = $this->studentApprovalService->listApprovalRequests($filters);

            return response()->json([
                'success' => true,
                'data' => collect($paginated->items())->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'user_id' => $item->user_id,
                        'user_name' => $item->user ? $item->user->display_name : 'Unknown User',
                        'user_email' => $item->user ? $item->user->email : 'N/A',
                        'status' => $item->status,
                        'student_institution' => $item->student_institution,
                        'student_email' => $item->student_email,
                        'student_birth_date' => $item->student_birth_date,
                        'county' => $item->county,
                        'documentation_url' => $item->documentation_url,
                        'additional_notes' => $item->additional_notes,
                        'submitted_at' => $item->submitted_at,
                        'reviewed_at' => $item->reviewed_at,
                        'reviewed_by' => $item->reviewed_by,
                        'rejection_reason' => $item->rejection_reason,
                        'admin_notes' => $item->admin_notes,
                        'expires_at' => $item->expires_at,
                    ];
                }),
                'pagination' => [
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve approval requests', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve approval requests'], 500);
        }
    }

    /**
     * Approve student request (admin only)
     *
     * Finalizes the review process by approving the request.
     * This action automatically creates a 'student' plan subscription for the user and activates it.
     * Restricted to administrators with appropriate permissions.
     *
     * @group Subscriptions - Student Approvals
     * @authenticated
     *
     * @urlParam request_id integer required The approval request ID. Example: 1
     * @bodyParam admin_notes string optional Internal notes for the approval. Example: Documents verified successfully.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Student request approved successfully",
     *   "data": {
     *     "status": "approved",
     *     "subscription_id": 1,
     *     "approved_at": "2025-12-07T14:00:00Z"
     *   }
     * }
     * @response 409 {
     *   "success": false,
     *   "message": "Request is not in pending status"
     * }
     */
    public function approveRequest(Request $request, $requestId): JsonResponse
    {
        // Check admin authorization
        if (!$request->user()->can('approve_student_approvals')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - inadequate permissions',
            ], 403);
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        try {
            $approvalRequest = $this->studentApprovalService->approveRequest(
                $requestId,
                $request->user()->id,
                $validated['admin_notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Student request approved successfully',
                'data' => [
                    'status' => $approvalRequest->status,
                    'approved_at' => $approvalRequest->reviewed_at,
                ],
            ]);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not in pending status')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request is not in pending status',
                ], 409);
            }
            Log::error('Student approval request approval failed', [
                'admin_id' => $request->user()->id,
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
     * Rejects a student approval request.
     * Administrators must provide a rejection reason (e.g., 'Unclear document') which will be visible to the user.
     * The user may then resolve the issue and re-apply.
     *
     * @group Subscriptions - Student Approvals
     * @authenticated
     *
     * @urlParam request_id integer required The approval request ID. Example: 1
     * @bodyParam rejection_reason string required Valid reason for rejection. Example: Documentation incomplete or unclear
     * @bodyParam admin_notes string optional Internal notes regarding the rejection. Example: Student uploaded a selfie instead of ID.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Student request rejected",
     *   "data": {
     *     "status": "rejected",
     *     "rejection_reason": "Documentation incomplete or unclear"
     *   }
     * }
     */
    public function rejectRequest(Request $request, $requestId): JsonResponse
    {
        // Check admin authorization
        if (!$request->user()->can('reject_student_approvals')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - inadequate permissions',
            ], 403);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:255',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        try {
            $approvalRequest = $this->studentApprovalService->rejectRequest(
                $requestId,
                $request->user()->id,
                $validated['rejection_reason'],
                $validated['admin_notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Student request rejected',
                'data' => [
                    'status' => $approvalRequest->status,
                    'rejection_reason' => $approvalRequest->rejection_reason,
                ],
            ]);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not in pending status')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request is not in pending status',
                ], 409);
            }
            Log::error('Student approval request rejection failed', [
                'admin_id' => $request->user()->id,
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
     * Checks if the logged-in user is allowed to submit a new student approval request.
     * Returns false if they already have a pending/active request or an active student subscription.
     * Use this to show/hide the application form.
     *
     * @group Subscriptions - Student Approvals
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "can_request": true,
     *   "reason": "You are eligible to request student plan"
     * }
     * @response 200 {
     *   "success": true,
     *   "can_request": false,
     *   "reason": "You already have an active student subscription"
     * }
     */
    public function canRequestStudentPlan(Request $request): JsonResponse
    {
        try {
            $result = $this->studentApprovalService->canRequestStudentPlan($request->user()->id);

            return response()->json([
                'success' => true,
                'can_request' => $result['can_request'],
                'reason' => $result['reason'],
                'existing_request' => $result['existing_request'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check student plan eligibility', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to check eligibility'], 500);
        }
    }
}
