<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDataRetentionSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredUserData implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $retentionSettings = UserDataRetentionSetting::getActiveSettings();

        $cleanupResults = [];

        foreach ($retentionSettings as $dataType => $retentionDays) {
            try {
                $result = $this->cleanupDataType($dataType, $retentionDays);
                $cleanupResults[$dataType] = $result;

                Log::info("Cleanup completed for {$dataType}", [
                    'retention_days' => $retentionDays,
                    'deleted_count' => $result['deleted_count'],
                    'errors' => $result['errors'],
                ]);
            } catch (\Exception $e) {
                Log::error("Cleanup failed for {$dataType}", [
                    'error' => $e->getMessage(),
                    'retention_days' => $retentionDays,
                ]);

                $cleanupResults[$dataType] = [
                    'deleted_count' => 0,
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        // Log the cleanup operation
        $this->logCleanupOperation($cleanupResults);

        Log::info('Expired user data cleanup completed', $cleanupResults);
    }

    /**
     * Clean up a specific data type
     */
    private function cleanupDataType(string $dataType, int $retentionDays): array
    {
        $cutoffDate = now()->subDays($retentionDays);
        $deletedCount = 0;
        $errors = [];

        switch ($dataType) {
            case 'user_accounts':
                $deletedCount = $this->cleanupExpiredUsers($cutoffDate);
                break;

            case 'audit_logs':
                $deletedCount = $this->cleanupExpiredAuditLogs($cutoffDate);
                break;

            case 'backups':
                $result = $this->cleanupExpiredBackups($cutoffDate);
                $deletedCount = $result['count'];
                $errors = $result['errors'];
                break;

            case 'temp_files':
                $result = $this->cleanupExpiredTempFiles($cutoffDate);
                $deletedCount = $result['count'];
                $errors = $result['errors'];
                break;

            case 'session_data':
                $deletedCount = $this->cleanupExpiredSessions($cutoffDate);
                break;

            case 'failed_jobs':
                $deletedCount = $this->cleanupExpiredFailedJobs($cutoffDate);
                break;

            default:
                Log::warning("Unknown data type for cleanup: {$dataType}");
                break;
        }

        return [
            'deleted_count' => $deletedCount,
            'errors' => $errors,
        ];
    }

    /**
     * Clean up expired soft-deleted users
     */
    private function cleanupExpiredUsers($cutoffDate): int
    {
        return User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoffDate)
            ->forceDelete();
    }

    /**
     * Clean up expired audit logs
     */
    private function cleanupExpiredAuditLogs($cutoffDate): int
    {
        return AuditLog::where('created_at', '<=', $cutoffDate)
            ->delete();
    }

    /**
     * Clean up expired backups
     */
    private function cleanupExpiredBackups($cutoffDate): array
    {
        $errors = [];
        $deletedCount = 0;

        try {
            $backupPath = storage_path('app/backups');
            if (!is_dir($backupPath)) {
                return ['count' => 0, 'errors' => []];
            }

            $files = glob($backupPath . '/*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) <= $cutoffDate->timestamp) {
                    if (unlink($file)) {
                        $deletedCount++;
                    } else {
                        $errors[] = "Failed to delete backup file: " . basename($file);
                    }
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Backup cleanup error: " . $e->getMessage();
        }

        return ['count' => $deletedCount, 'errors' => $errors];
    }

    /**
     * Clean up expired temporary files
     */
    private function cleanupExpiredTempFiles($cutoffDate): array
    {
        $errors = [];
        $deletedCount = 0;

        try {
            $tempPath = storage_path('app/temp');
            if (!is_dir($tempPath)) {
                return ['count' => 0, 'errors' => []];
            }

            $files = glob($tempPath . '/*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) <= $cutoffDate->timestamp) {
                    if (unlink($file)) {
                        $deletedCount++;
                    } else {
                        $errors[] = "Failed to delete temp file: " . basename($file);
                    }
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Temp file cleanup error: " . $e->getMessage();
        }

        return ['count' => $deletedCount, 'errors' => $errors];
    }

    /**
     * Clean up expired sessions
     */
    private function cleanupExpiredSessions($cutoffDate): int
    {
        return DB::table('sessions')
            ->where('last_activity', '<=', $cutoffDate->timestamp)
            ->delete();
    }

    /**
     * Clean up expired failed jobs
     */
    private function cleanupExpiredFailedJobs($cutoffDate): int
    {
        return DB::table('failed_jobs')
            ->where('failed_at', '<=', $cutoffDate->toDateTimeString())
            ->delete();
    }

    /**
     * Log the cleanup operation
     */
    private function logCleanupOperation(array $results): void
    {
        $totalDeleted = array_sum(array_column($results, 'deleted_count'));
        $allErrors = [];

        foreach ($results as $dataType => $result) {
            if (!empty($result['errors'])) {
                $allErrors = array_merge($allErrors, $result['errors']);
            }
        }

        try {
            AuditLog::create([
                'action' => 'system_cleanup',
                'model_type' => 'System',
                'model_id' => 0,
                'user_id' => null, // System operation
                'old_values' => null,
                'new_values' => json_encode([
                    'cleanup_results' => $results,
                    'total_deleted' => $totalDeleted,
                    'errors' => $allErrors,
                ]),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => 'System Cleanup Job',
                'notes' => "Automated cleanup of expired data. Total items deleted: {$totalDeleted}",
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log cleanup operation', [
                'error' => $e->getMessage(),
                'results' => $results,
            ]);
        }
    }
}
