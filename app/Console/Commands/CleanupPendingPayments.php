<?php

namespace App\Console\Commands;

use App\Jobs\CleanupPendingPaymentsJob;
use App\Models\DataDestructionCommand;
use App\Models\UserDataRetentionSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupPendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:cleanup-pending {--sync} {--dry-run} {--days=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark expired pending payments as completed or deleted based on retention settings';

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

            $this->info("💳 Pending Payments Cleanup Command");
            $this->info("{'━'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━}");
            $this->line("Retention Days: <fg=cyan>{$retentionDays}</>");
            $this->line("Sync Mode: <fg=yellow>" . ($sync ? 'Yes' : 'No [Queued]') . "</>");
            $this->line("Dry Run: <fg=yellow>" . ($dryRun ? 'Yes [Preview Mode]' : 'No [Will Mark as Expired]') . "</>");
            $this->line("");

            if ($sync || $dryRun) {
                // Run job immediately (sync mode or dry-run)
                $this->info("⏳ Running cleanup job synchronously...");
                app()->call([new CleanupPendingPaymentsJob($retentionDays, $dryRun), 'handle']);
                $this->info("✅ Cleanup job completed");

                if ($dryRun) {
                    $this->line("<fg=yellow>Note: This was a dry-run. No payments were marked as expired.</>");
                }
            } else {
                // Queue job for async execution
                $this->info("📤 Dispatching cleanup job to queue...");
                CleanupPendingPaymentsJob::dispatch($retentionDays, $dryRun);
                $this->info("✅ Job dispatched to queue for processing");
            }

            Log::info('CleanupPendingPayments command executed', [
                'retention_days' => $retentionDays,
                'sync' => $sync,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('CleanupPendingPayments command failed', [
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
        $setting = UserDataRetentionSetting::where('data_type', 'pending_payments')
            ->where('is_enabled', true)
            ->first();

        return $setting?->retention_days ?? 30; // Default: 30 days
    }
}
