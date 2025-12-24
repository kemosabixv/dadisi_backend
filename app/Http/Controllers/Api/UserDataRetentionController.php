<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDataRetentionSetting;
use App\Models\SchedulerSetting;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserDataRetentionController extends Controller
{
    public function __construct()
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
        $query = UserDataRetentionSetting::with('updatedBy:id,username');

        if ($request->has('data_type')) {
            $query->where('data_type', $request->data_type);
        }

        $settings = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
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
        return response()->json([
            'success' => true,
            'data' => $retention->load('updatedBy:id,username'),
        ]);
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
    public function update(Request $request, UserDataRetentionSetting $retention): JsonResponse
    {
        $validated = $request->validate([
            'retention_days' => 'nullable|integer|min:0|max:3650', // Max 10 years
            'retention_minutes' => 'nullable|integer|min:1|max:525600', // Max 1 year in minutes
            'auto_delete' => 'boolean',
            'description' => 'nullable|string|max:500',
        ]);

        $oldValues = $retention->only(array_keys($validated));

        $validated['updated_by'] = auth()->id();
        $retention->update($validated);

        // Audit log
        $this->logAuditAction('update', UserDataRetentionSetting::class, $retention->id, $oldValues, $validated,
            "Updated retention settings for {$retention->data_type}");

        return response()->json([
            'success' => true,
            'message' => 'Retention setting updated successfully',
            'data' => $retention->load('updatedBy:id,username'),
        ]);
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
        $settings = UserDataRetentionSetting::getActiveSettings();

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
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
    public function updateRetentionDays(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data_type' => 'required|string|in:orphaned_media,temporary_media,user_accounts,audit_logs,session_data,failed_jobs,temp_files,backups,pending_payments,webhook_events',
            'retention_days' => 'nullable|integer|min:0|max:3650',
            'retention_minutes' => 'nullable|integer|min:1|max:525600', // Max 1 year in minutes
        ]);

        $setting = UserDataRetentionSetting::where('data_type', $validated['data_type'])->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => "Data type '{$validated['data_type']}' not found",
            ], 404);
        }

        $oldValues = [
            'retention_days' => $setting->retention_days,
            'retention_minutes' => $setting->retention_minutes,
        ];

        $updateData = ['updated_by' => auth()->id()];
        
        if (isset($validated['retention_days'])) {
            $updateData['retention_days'] = $validated['retention_days'];
        }
        if (isset($validated['retention_minutes'])) {
            $updateData['retention_minutes'] = $validated['retention_minutes'];
        }

        $setting->update($updateData);

        $this->logAuditAction('update', UserDataRetentionSetting::class, $setting->id,
            $oldValues,
            array_intersect_key($updateData, ['retention_days' => 1, 'retention_minutes' => 1]),
            "Updated retention for {$validated['data_type']}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Retention settings updated successfully',
            'data' => $setting->only(['data_type', 'retention_days', 'retention_minutes']),
        ]);
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
    public function updateScheduler(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command_name' => 'required|string',
            'run_time' => 'required|date_format:H:i',
            'frequency' => 'in:daily,weekly,monthly,hourly',
            'enabled' => 'boolean',
        ]);

        $scheduler = SchedulerSetting::firstOrCreate(
            ['command_name' => $validated['command_name']],
            [
                'run_time' => $validated['run_time'],
                'frequency' => $validated['frequency'] ?? 'daily',
                'enabled' => $validated['enabled'] ?? true,
                'updated_by' => auth()->id(),
            ]
        );

        // If scheduler already existed, update it
        if ($scheduler->wasRecentlyCreated === false) {
            $oldValues = $scheduler->only(['run_time', 'frequency', 'enabled']);
            $scheduler->update(array_merge($validated, ['updated_by' => auth()->id()]));

            $this->logAuditAction('update', SchedulerSetting::class, $scheduler->id,
                $oldValues,
                $scheduler->only(['run_time', 'frequency', 'enabled']),
                "Updated scheduler '{$validated['command_name']}' to run at {$validated['run_time']}"
            );
        } else {
            $this->logAuditAction('create', SchedulerSetting::class, $scheduler->id,
                null,
                $scheduler->only(['command_name', 'run_time', 'frequency', 'enabled']),
                "Created scheduler '{$validated['command_name']}'"
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Scheduler updated successfully',
            'data' => $scheduler->only(['command_name', 'run_time', 'frequency', 'enabled']),
        ]);
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
        $schedulers = SchedulerSetting::latest()->get();

        return response()->json([
            'success' => true,
            'data' => $schedulers,
        ]);
    }

    /**
     * Log audit actions
     */
    private function logAuditAction(string $action, string $modelType, int $modelId, ?array $oldValues, ?array $newValues, ?string $notes = null): void
    {
        try {
            AuditLog::create([
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'user_id' => auth()->id(),
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'notes' => $notes,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create audit log', [
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
