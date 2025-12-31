<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * Audit Log Service
 *
 * Handles audit logging for tracking changes to models and system actions.
 */
class AuditLogService
{
    /**
     * Log an audit action
     *
     * @param string $action The action performed (create, update, delete, etc.)
     * @param string $modelType The fully qualified model class
     * @param int|string $modelId The ID of the affected model
     * @param array|null $oldValues The previous values (for updates/deletes)
     * @param array|null $newValues The new values (for creates/updates)
     * @param string|null $description Optional description of the action
     * @return void
     */
    public function log(
        string $action,
        string $modelType,
        int|string $modelId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null
    ): void {
        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'description' => $description,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Log::info("Audit log recorded: {$action}", [
                'model_type' => $modelType,
                'model_id' => $modelId,
                'description' => $description,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log audit action', [
                'error' => $e->getMessage(),
                'action' => $action,
            ]);
        }
    }
}
