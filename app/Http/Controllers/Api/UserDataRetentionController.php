<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDataRetentionSetting;
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
     * Display a listing of retention settings
     *
     * @group Data Retention Management
     * @authenticated
     * @description List all data retention settings (Super Admin only)
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
     * Display the specified retention setting
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
     * Update the specified retention setting
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
            'retention_days' => 'required|integer|min:1|max:3650', // Max 10 years
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
     * Get current retention settings summary
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
