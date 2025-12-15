<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AutoRenewalJob;
use App\Services\AutoRenewalService;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin - Auto Renewal Jobs
 * Manage automatic subscription renewal jobs, view status, retry failed renewals, and cancel pending jobs.
 * All endpoints require Sanctum authentication and admin authorization.
 */
class AutoRenewalJobController extends Controller
{
    public function __construct()
    {
        // Policy or middleware should restrict this to admins/super-admins in production
        $this->middleware('auth:sanctum');
    }

    /**
     * List all auto renewal jobs with pagination and status filtering.
     *
     * Retrieves paginated list of subscription renewal jobs across all users,
     * with optional filtering by job status (scheduled, completed, failed, cancelled, retry_scheduled).
     * Useful for monitoring renewal operations and identifying failed renewals.
     *
     * @authenticated
     * @queryParam status string Filter by job status (scheduled, completed, failed, cancelled, retry_scheduled). Example: failed
     * @queryParam per_page integer Items per page. Default: 25. Example: 50
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "subscription_id": 5,
     *       "user_id": 10,
     *       "status": "scheduled",
     *       "scheduled_at": "2025-12-15T02:00:00Z",
     *       "completed_at": null,
     *       "failure_reason": null,
     *       "subscription": {"id": 5, "user_id": 10, "status": "active"},
     *       "user": {"id": 10, "name": "John Doe", "email": "john@example.com"}
     *     }
     *   ],
     *   "pagination": {"total": 45, "per_page": 25, "current_page": 1, "last_page": 2}
     * }
     * @response 403 {"success": false, "message": "This action is unauthorized"}
     */
    public function index(Request $request): JsonResponse
    {
        $query = AutoRenewalJob::query()->with(['subscription', 'user']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->query('per_page', 25);

        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Show details of a specific auto renewal job.
     *
     * Retrieves detailed information about a single renewal job including associated
     * subscription and user details. Use this to inspect renewal status, failure reasons,
     * and historical data for a specific job.
     *
     * @authenticated
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "subscription_id": 5,
     *     "user_id": 10,
     *     "status": "failed",
     *     "scheduled_at": "2025-12-15T02:00:00Z",
     *     "completed_at": "2025-12-15T02:15:30Z",
     *     "failure_reason": "Payment declined",
     *     "retry_count": 1,
     *     "next_retry_at": "2025-12-16T02:00:00Z",
     *     "subscription": {"id": 5, "user_id": 10, "plan_id": 2, "status": "grace_period"},
     *     "user": {"id": 10, "name": "John Doe", "email": "john@example.com"}
     *   }
     * }
     * @response 403 {"success": false, "message": "This action is unauthorized"}
     * @response 404 {"success": false, "message": "Renewal job not found"}
     */
    public function show($id): JsonResponse
    {
        $job = AutoRenewalJob::with(['subscription', 'user'])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $job]);
    }

    /**
     * Retry a failed or scheduled renewal job immediately.
     *
     * Triggers an immediate renewal attempt for a failed or scheduled job without waiting
     * for the regular schedule. Useful for testing renewal logic or forcing immediate retry
     * after issue resolution. Uses AutoRenewalService to process the renewal.
     *
     * @authenticated
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 2,
     *     "subscription_id": 5,
     *     "status": "scheduled",
     *     "scheduled_at": "2025-12-15T02:00:00Z",
     *     "completed_at": null,
     *     "message": "Renewal job created for immediate processing"
     *   }
     * }
     * @response 403 {"success": false, "message": "This action is unauthorized"}
     * @response 404 {"success": false, "message": "Subscription not found for this job"}
     */
    public function retry($id): JsonResponse
    {
        $job = AutoRenewalJob::with('subscription')->findOrFail($id);

        $subscription = $job->subscription;
        if (!$subscription) {
            return response()->json(['success' => false, 'message' => 'Subscription not found for this job'], 404);
        }

        // Use the AutoRenewalService to perform an immediate retry
        $service = new AutoRenewalService();
        $newJob = $service->processSubscriptionRenewal($subscription);

        return response()->json(['success' => true, 'data' => $newJob]);
    }

    /**
     * Cancel a pending or scheduled renewal job.
     *
     * Cancels a renewal job that is pending or scheduled. The associated subscription
     * will enter a grace period state. Useful for pausing automatic renewal while user
     * resolves payment issues or account problems. Only affects the job, not the subscription.
     *
     * @authenticated
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "subscription_id": 5,
     *     "status": "cancelled",
     *     "scheduled_at": "2025-12-15T02:00:00Z",
     *     "cancelled_at": "2025-12-12T10:30:00Z"
     *   }
     * }
     * @response 403 {"success": false, "message": "This action is unauthorized"}
     * @response 404 {"success": false, "message": "Renewal job not found"}
     */
    public function cancel($id): JsonResponse
    {
        $job = AutoRenewalJob::findOrFail($id);

        $job->status = 'cancelled';
        $job->save();

        return response()->json(['success' => true, 'data' => $job]);
    }
}
