<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CleanupAuditLogsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     *
     * @param int|null $retentionDays Days to retain audit logs. Default: 365 (1 year)
     * @param bool $dryRun If true, report what would be deleted without deleting
     */
    public function __construct(
        protected ?int $retentionDays = null,
        protected bool $dryRun = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $retentionDays = $this->retentionDays ?? 365; // Default: 1 year for audit logs
        $cutoffDate = now()->subDays($retentionDays);

        try {
            // Query old audit log records
            $oldAuditLogs = AuditLog::where('created_at', '<', $cutoffDate)->get();
            $deletedCount = $oldAuditLogs->count();

            if (!$this->dryRun && $deletedCount > 0) {
                // Delete audit log records
                AuditLog::where('created_at', '<', $cutoffDate)->delete();
            }

            // Compile audit log statistics
            $auditStats = [
                'by_model_type' => $oldAuditLogs->groupBy('auditable_type')
                    ->map(fn ($logs) => $logs->count())
                    ->toArray(),
                'by_action' => $oldAuditLogs->groupBy('action')
                    ->map(fn ($logs) => $logs->count())
                    ->toArray(),
                'date_range' => [
                    'oldest' => $oldAuditLogs->min('created_at')?->toIso8601String(),
                    'newest' => $oldAuditLogs->max('created_at')?->toIso8601String(),
                ],
            ];

            Log::info('Audit logs cleanup completed', [
                'retention_days' => $retentionDays,
                'deleted_count' => $deletedCount,
                'dry_run' => $this->dryRun,
                'cutoff_date' => $cutoffDate->toIso8601String(),
                'audit_stats' => $auditStats,
                'timestamp' => now(),
            ]);

            // Track in data destruction command if not dry run
            if (!$this->dryRun && $deletedCount > 0) {
                $this->recordDestructionCommand($deletedCount, $auditStats);
            }
        } catch (\Exception $e) {
            Log::error('Audit logs cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Record the cleanup operation in data_destruction_commands table.
     *
     * @param int $affectedRecords Count of deleted audit log records
     * @param array $auditStats Statistics breakdown by model type and action
     */
    private function recordDestructionCommand(int $affectedRecords, array $auditStats): void
    {
        try {
            $dataDestructionCommand = \App\Models\DataDestructionCommand::where(
                'command_name',
                'audit:cleanup-old'
            )->first();

            if ($dataDestructionCommand) {
                $dataDestructionCommand->update([
                    'affected_records_count' => $affectedRecords,
                    'last_run_at' => now(),
                    'metadata' => [
                        'audit_stats' => $auditStats,
                        'timestamp' => now()->toIso8601String(),
                    ],
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to record data destruction command', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
