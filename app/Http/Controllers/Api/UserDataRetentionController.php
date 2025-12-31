<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateUserDataRetentionRequest;
use App\Http\Requests\Api\UpdateRetentionDaysRequest;
use App\Http\Requests\Api\UpdateSchedulerRequest;
use App\Models\UserDataRetentionSetting;
use App\Models\SchedulerSetting;
use App\Models\AuditLog;
use App\Services\Contracts\UserDataRetentionServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserDataRetentionController extends Controller
{
    public function __construct(private UserDataRetentionServiceContract $retentionService)
    {
        $this->authorizeResource(UserDataRetentionSetting::class, 'retention');
    }

    /**
     * List Retention Policies
     *
     * Retrieves a detailed list of all configured data retention policies.
     * These policies determine how long various types of data (e.g., logs, user history) are kept before being automatically purged.
     *
     * @group Data Retention Management
     * @groupDescription Administrative endpoints for managing data retention policies, automated cleanup schedules, and compliance settings.
     * @authenticated
     * @description List all active data retention policies. Only accessible by Super Admins.
     *
     * @queryParam data_type Filter by data type. Example: user_accounts
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "data_type": "user_accounts",
     *       "retention_days": 90,
     *       "auto_delete": true,
     *       "description": "User account data retention",
     *       "updated_by": 1,
     *       "created_at": "2025-01-01T00:00:00Z",
     *       "updated_at": "2025-01-01T00:00:00Z"
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = ['data_type' => $request->input('data_type')];
            $settings = $this->retentionService->listPolicies($filters);

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve retention policies', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve retention policies'], 500);
        }
    }

    /**
     * Get Retention Policy Details
     *
     * Retrieves the specific configuration for a single data retention policy.
     * Includes information on retention duration, auto-delete status, and the administrator who last modified it.
     *
     * @group Data Retention Management
     * @authenticated
     * @description Get a specific retention setting (Super Admin only)
     *
     * @urlParam retention required The retention setting ID
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "data_type": "user_accounts",
     *     "retention_days": 90,
     *     "auto_delete": true,
     *     "description": "User account data retention",
     *     "updated_by": 1,
     *     "updated_by_user": {"username": "admin"}
     *   }
     * }
     */
    public function show(UserDataRetentionSetting $retention): JsonResponse
    {
        try {
            $data = $this->retentionService->getPolicyDetails($retention->id);
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve retention policy', ['error' => $e->getMessage(), 'retention_id' => $retention->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve retention policy'], 500);
        }
    }

/**
     * Update Retention Policy
     *
     * Modifies the parameters of an existing data retention policy.
     * Allows administrators to change the retention period (in days), toggle automatic deletion, or update the policy description.
     * All changes are audit-logged.
     *
     * @group Data Retention Management
     * @authenticated
     * @description Update retention settings (Super Admin only)
     *
     * @urlParam retention required The retention setting ID
     * @bodyParam retention_days integer required Retention period in days. Example: 90
     * @bodyParam auto_delete boolean Enable/disable auto deletion. Example: true
     * @bodyParam description string Description of the setting. Example: User account data retention
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Retention setting updated successfully",
     *   "data": {
     *     "id": 1,
     *     "data_type": "user_accounts",
     *     "retention_days": 90,
     *     "auto_delete": true
     *   }
     * }
     */
    public function update(UpdateUserDataRetentionRequest $request, UserDataRetentionSetting $retention): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->retentionService->updatePolicy($retention->id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Retention setting updated successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update retention policy', ['error' => $e->getMessage(), 'retention_id' => $retention->id]);
            return response()->json(['success' => false, 'message' => 'Failed to update retention policy'], 500);
        }
    }

    /**
     * Get Retention Summary
     *
     * Provides a quick overview of the active retention periods for key data types.
     * Useful for dashboard widgets or compliance reporting.
     *
     * @group Data Retention Management
     * @authenticated
     * @description Get summary of all active retention settings (Super Admin only)
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "user_accounts": 90,
     *     "audit_logs": 365,
     *     "backups": 730
     *   }
     * }
     */
    public function summary(): JsonResponse
    {
        try {
            $settings = $this->retentionService->getSummary();

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve retention summary', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve retention summary'], 500);
        }
    }

    /**
     * Update Retention Period (Quick Action)
     *
     * A streamlined endpoint to quickly update the retention duration (days) for a specific data type.
     * This is an alternative to the full update endpoint when only the day count needs changing.
     *
     * @group Data Retention Management
     * @authenticated
     * @description Update retention cutoff days for specific data types (Super Admin only)
     *
     * @bodyParam data_type string required The data type (e.g., 'orphaned_media'). Example: orphaned_media
     * @bodyParam retention_days integer required Retention period in days. Example: 90
     *
    * @response 200 {
     *   "success": true,
     *   "message": "Retention days updated successfully",
     *   "data": {
     *     "data_type": "orphaned_media",
     *     "retention_days": 90
     *   }
     * }
    * @response 404 {
    *   "success": false,
    *   "message": "Data type 'orphaned_media' not found"
    * }
     */
    public function updateRetentionDays(UpdateRetentionDaysRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->retentionService->updateRetentionDays($validated['data_type'], $validated);

            return response()->json([
                'success' => true,
                'message' => 'Retention settings updated successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update retention days', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to update retention days'], 500);
        }
    }

    /**
     * Configure Cleanup Schedule
     *
     * Configures the timing and frequency of automated cleanup commands.
     * Allows administrators to determine exactly when system maintenance tasks (like purging old data) run to minimize impact on active users.
     *
     * @group Data Retention Management
     * @authenticated
     * @description Update when scheduled commands run (Super Admin only)
     *
     * @bodyParam command_name string required The command name (e.g., 'media:cleanup'). Example: media:cleanup
     * @bodyParam run_time string required Run time in HH:MM format. Example: 03:00
     * @bodyParam frequency string Frequency: daily, weekly, monthly, hourly. Example: daily
     * @bodyParam enabled boolean Enable/disable the scheduler. Example: true
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Scheduler updated successfully",
     *   "data": {
     *     "command_name": "media:cleanup",
     *     "run_time": "03:00",
     *     "frequency": "daily",
     *     "enabled": true
     *   }
     * }
     */
    public function updateScheduler(UpdateSchedulerRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->retentionService->updateScheduler($validated);

            return response()->json([
                'success' => true,
                'message' => 'Scheduler updated successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update scheduler', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to update scheduler'], 500);
        }
    }

    /**
     * List Cleanup Schedules
     *
     * Retrieves a list of all defined system maintenance schedules.
     * Shows which commands are enabled, their frequency (e.g., daily), and the specific time they are set to execute.
     *
     * @group Data Retention Management
     * @authenticated
     * @description Retrieve all scheduler settings with current run times (Super Admin only)
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "command_name": "media:cleanup",
     *       "run_time": "03:00",
     *       "frequency": "daily",
     *       "enabled": true
     *     }
     *   ]
     * }
     */
    public function getSchedulers(): JsonResponse
    {
        try {
            $schedulers = $this->retentionService->listSchedulers();

            return response()->json([
                'success' => true,
                'data' => $schedulers,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve schedulers', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve schedulers'], 500);
        }
    }
}
