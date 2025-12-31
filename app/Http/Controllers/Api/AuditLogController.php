<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Audit Log Controller
 *
 * Read-only endpoints for viewing audit logs.
 * Only accessible by users with appropriate permissions.
 */
class AuditLogController extends Controller
{
    /**
     * List audit logs with filtering
     *
     * @group Audit Logs
     * @authenticated
     * @description Retrieve audit logs with optional filtering by model type, action, or user.
     *
     * @queryParam model_type string Filter by model type (e.g., App\Models\User). Example: App\Models\User
     * @queryParam action string Filter by action (create, update, delete, etc.). Example: create
     * @queryParam user_id integer Filter by user ID who performed the action. Example: 1
     * @queryParam model_id integer Filter by affected model ID. Example: 123
     * @queryParam date_from date Filter logs from this date. Example: 2025-01-01
     * @queryParam date_to date Filter logs until this date. Example: 2025-12-31
     * @queryParam page integer Page number for pagination. Example: 1
     * @queryParam per_page integer Results per page. Example: 50
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 1,
     *         "action": "create",
     *         "model_type": "App\Models\User",
     *         "model_id": 123,
     *         "user_id": 1,
     *         "old_values": null,
     *         "new_values": {"name": "John Doe"},
     *         "ip_address": "192.168.1.1",
     *         "user_agent": "Mozilla/5.0...",
     *         "notes": "User created",
     *         "created_at": "2025-01-01T12:00:00Z"
     *       }
     *     ],
     *     "total": 100,
     *     "per_page": 50
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = AuditLog::query();

            // Filter by model type
            if ($request->filled('model_type')) {
                $query->where('model_type', $request->input('model_type'));
            }

            // Filter by action
            if ($request->filled('action')) {
                $query->where('action', $request->input('action'));
            }

            // Filter by user ID (who performed the action)
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }

            // Filter by affected model ID
            if ($request->filled('model_id')) {
                $query->where('model_id', $request->input('model_id'));
            }

            // Filter by date range
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }

            $perPage = $request->input('per_page', 50);
            $logs = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => AuditLogResource::collection($logs),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage(),
                    'last_page' => $logs->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve audit logs', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve audit logs'], 500);
        }
    }

    /**
     * Get specific audit log details
     *
     * @group Audit Logs
     * @authenticated
     *
     * @urlParam id integer required The audit log ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "action": "create",
     *     "model_type": "App\Models\User",
     *     "model_id": 123,
     *     "user_id": 1,
     *     "old_values": null,
     *     "new_values": {"name": "John Doe"},
     *     "ip_address": "192.168.1.1",
     *     "user_agent": "Mozilla/5.0...",
     *     "notes": "User created",
     *     "created_at": "2025-01-01T12:00:00Z"
     *   }
     * }
     */
    public function show(AuditLog $auditLog): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => new AuditLogResource($auditLog)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve audit log', ['error' => $e->getMessage(), 'id' => $auditLog->id]);
            return response()->json(['success' => false, 'message' => 'Audit log not found'], 404);
        }
    }

    /**
     * Get audit logs for a specific model
     *
     * @group Audit Logs
     * @authenticated
     *
     * @queryParam model_type string required The fully qualified model class. Example: App\Models\User
     * @queryParam model_id integer required The ID of the model. Example: 123
     * @queryParam page integer Page number. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "action": "create",
     *       "model_type": "App\Models\User",
     *       "model_id": 123,
     *       "user_id": 1,
     *       "created_at": "2025-01-01T12:00:00Z"
     *     }
     *   ]
     * }
     */
    public function getModelAuditLog(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'model_type' => 'required|string',
                'model_id' => 'required|integer',
            ]);

            $logs = AuditLog::where('model_type', $request->input('model_type'))
                ->where('model_id', $request->input('model_id'))
                ->latest()
                ->paginate($request->input('per_page', 50));

            return response()->json([
                'success' => true,
                'data' => AuditLogResource::collection($logs),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve model audit logs', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve audit logs'], 500);
        }
    }

    /**
     * Get audit logs for a specific user's actions
     *
     * @group Audit Logs
     * @authenticated
     *
     * @queryParam user_id integer required The user ID. Example: 1
     * @queryParam page integer Page number. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "action": "create",
     *       "model_type": "App\Models\User",
     *       "user_id": 1,
     *       "created_at": "2025-01-01T12:00:00Z"
     *     }
     *   ]
     * }
     */
    public function getUserAuditLog(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|integer',
            ]);

            $logs = AuditLog::where('user_id', $request->input('user_id'))
                ->latest()
                ->paginate($request->input('per_page', 50));

            return response()->json([
                'success' => true,
                'data' => AuditLogResource::collection($logs),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user audit logs', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve audit logs'], 500);
        }
    }
}
