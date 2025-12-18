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
use App\Models\SubscriptionEnhancement;

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
     * @bodyParam birth_date date required Date of birth for age verification (must be at least 16 years old). Example: 2005-01-15
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
     *     "student_institution": "University of Nairobi",
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
