<?php

namespace App\Console\Commands;

use App\Jobs\CleanupTemporaryMediaJob;
use App\Models\DataDestructionCommand;
use App\Models\UserDataRetentionSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupTemporaryMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:cleanup-temporary {--sync} {--dry-run} {--days=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete temporary media files that have exceeded retention period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Get retention days from option or database setting
            $retentionDays = $this->option('days')
                ? (int) $this->option('days')
                : $this->getRetentionDays();

            $dryRun = $this->option('dry-run');
            $sync = $this->option('sync');

            $this->info("🖼️  Temporary Media Cleanup Command");
            $this->info("{'━'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━}");
            $this->line("Retention Days: <fg=cyan>{$retentionDays}</>");
            $this->line("Sync Mode: <fg=yellow>" . ($sync ? 'Yes' : 'No [Queued]') . "</>");
            $this->line("Dry Run: <fg=yellow>" . ($dryRun ? 'Yes [Preview Mode]' : 'No [Will Delete]') . "</>");
            $this->line("");

            if ($sync || $dryRun) {
                // Run job immediately (sync mode or dry-run)
                $this->info("⏳ Running cleanup job synchronously...");
                app()->call([new CleanupTemporaryMediaJob($retentionDays, $dryRun), 'handle']);
                $this->info("✅ Cleanup job completed");

                if ($dryRun) {
                    $this->line("<fg=yellow>Note: This was a dry-run. No files were deleted.</>");
                }
            } else {
                // Queue job for async execution
                $this->info("📤 Dispatching cleanup job to queue...");
                CleanupTemporaryMediaJob::dispatch($retentionDays, $dryRun);
                $this->info("✅ Job dispatched to queue for processing");
            }

            Log::info('CleanupTemporaryMedia command executed', [
                'retention_days' => $retentionDays,
                'sync' => $sync,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('CleanupTemporaryMedia command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Get retention days from database setting.
     *
     * @return int
     */
    private function getRetentionDays(): int
    {
        $setting = UserDataRetentionSetting::where('data_type', 'temporary_media')
            ->where('is_enabled', true)
            ->first();

        return $setting?->retention_days ?? 7; // Default: 7 days
    }
}
