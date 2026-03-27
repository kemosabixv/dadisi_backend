<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\Contracts\MediaServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CleanupTemporaryMediaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     *
     * @param int|null $retentionDays Days to retain temporary media. Default: 7
     * @param bool $dryRun If true, report what would be deleted without deleting
     */
    public function __construct(
        protected ?int $retentionDays = null,
        protected bool $dryRun = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MediaServiceContract $mediaService): void
    {
        $retentionDays = $this->retentionDays ?? 7; // Default: 7 days for temporary media
        $cutoffDate = now()->subDays($retentionDays);

        try {
            $temporaryMedia = Media::where(function ($query) use ($cutoffDate) {
                // Path 1: Expired Temporary Media (All Roots)
                $query->whereNotNull('temporary_until')
                      ->where('temporary_until', '<', $cutoffDate);
            })
            ->orWhere(function ($query) use ($retentionDays) {
                // Path 2: Public Orphans (Usage Count 0)
                $query->where('root_type', 'public')
                      ->where('usage_count', 0)
                      ->where('created_at', '<', now()->subDays($retentionDays));
            })
            ->get();

            $deletedCount = 0;
            $failedCount = 0;
            $diskSpaceFreed = 0;

            foreach ($temporaryMedia as $media) {
                try {
                    if ($this->dryRun) {
                        $diskSpaceFreed += $media->file_size ?? 0;
                        $deletedCount++;
                        continue;
                    }

                    // Keep CAS cleanup logic in service
                    $owner = $media->user ?? $media->owner ?? null;
                    if (!$owner) {
                        $meta = ['media_id' => $media->id, 'warning' => 'no owner'];
                        Log::warning('Skipping temporary media with missing owner', $meta);
                        $failedCount++;
                        continue;
                    }

                    $mediaService->deleteMedia($owner, $media);
                    $deletedCount++;
                    $diskSpaceFreed += $media->file_size ?? 0;
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::warning('Failed to delete temporary media', [
                        'media_id' => $media->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Temporary media cleanup completed', [
                'retention_days' => $retentionDays,
                'deleted_count' => $deletedCount,
                'failed_count' => $failedCount,
                'disk_space_freed_mb' => round($diskSpaceFreed / 1024 / 1024, 2),
                'dry_run' => $this->dryRun,
                'timestamp' => now(),
            ]);

            if (!$this->dryRun && $deletedCount > 0) {
                $this->recordDestructionCommand($deletedCount, $diskSpaceFreed);
            }
        } catch (\Exception $e) {
            Log::error('Temporary media cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Record the cleanup operation in data_destruction_commands table.
     *
     * @param int $affectedRecords Count of deleted media files
     * @param int $diskSpaceFreed Bytes freed from storage
     */
    private function recordDestructionCommand(int $affectedRecords, int $diskSpaceFreed): void
    {
        try {
            $dataDestructionCommand = \App\Models\DataDestructionCommand::where(
                'command_name',
                'media:cleanup-temporary'
            )->first();

            if ($dataDestructionCommand) {
                $dataDestructionCommand->update([
                    'affected_records_count' => $affectedRecords,
                    'last_run_at' => now(),
                    'metadata' => [
                        'disk_space_freed_bytes' => $diskSpaceFreed,
                        'disk_space_freed_mb' => round($diskSpaceFreed / 1024 / 1024, 2),
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
