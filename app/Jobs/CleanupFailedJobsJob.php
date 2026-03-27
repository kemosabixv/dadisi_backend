<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupFailedJobsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     *
     * @param int|null $retentionDays Days to retain failed job records. Default: 60
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
        $retentionDays = $this->retentionDays ?? 60; // Default: 60 days for failed job records
        $cutoffDate = now()->subDays($retentionDays);

        try {
            // Count records to be deleted
            $deletedCount = DB::table('failed_jobs')
                ->where('failed_at', '<', $cutoffDate)
                ->count();

            if (!$this->dryRun) {
                // Delete failed job records
                DB::table('failed_jobs')
                    ->where('failed_at', '<', $cutoffDate)
                    ->delete();
            }

            Log::info('Failed jobs cleanup completed', [
                'retention_days' => $retentionDays,
                'deleted_count' => $deletedCount,
                'dry_run' => $this->dryRun,
                'cutoff_date' => $cutoffDate->toIso8601String(),
                'timestamp' => now(),
            ]);

            // Track in data destruction command if not dry run
            if (!$this->dryRun && $deletedCount > 0) {
                $this->recordDestructionCommand($deletedCount);
            }
        } catch (\Exception $e) {
            Log::error('Failed jobs cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Record the cleanup operation in data_destruction_commands table.
     *
     * @param int $affectedRecords Count of deleted failed job records
     */
    private function recordDestructionCommand(int $affectedRecords): void
    {
        try {
            $dataDestructionCommand = \App\Models\DataDestructionCommand::where(
                'command_name',
                'failed-jobs:cleanup'
            )->first();

            if ($dataDestructionCommand) {
                $dataDestructionCommand->update([
                    'affected_records_count' => $affectedRecords,
                    'last_run_at' => now(),
                    'metadata' => [
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
