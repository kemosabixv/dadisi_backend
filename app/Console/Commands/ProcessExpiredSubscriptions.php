<?php

namespace App\Console\Commands;

use App\Jobs\ProcessExpiredSubscriptionsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:process-expired {--sync} {--dry-run} {--grace-days=14}';

    protected $description = 'Process expired subscriptions: enter grace period or downgrade to free tier';

    /**
     * Execute the console command.
     * 
     * CRITICAL: This command triggers member downgrades!
     * 
     * Two phases:
     * 1. Subscriptions that just expired → enter grace period (14 days default)
     * 2. Subscriptions with expired grace periods → downgrade to free tier
     * 
     * Options:
     * --sync          Run immediately (for testing/debugging)
     * --dry-run       Preview what would happen without making changes
     * --grace-days    Number of days for grace period (default: 14)
     */
    public function handle()
    {
        try {
            $graceDays = (int) $this->option('grace-days');
            $dryRun = $this->option('dry-run');
            $sync = $this->option('sync');

            $this->info("💳 Process Expired Subscriptions Command");
            $this->info("{'━'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━}");
            $this->line("Grace Period Days: <fg=cyan>{$graceDays}</>");
            $this->line("Sync Mode: <fg=yellow>" . ($sync ? 'Yes' : 'No [Queued]') . "</>");
            $this->line("Dry Run: <fg=yellow>" . ($dryRun ? 'Yes [Preview Mode]' : 'No [Will Downgrade]') . "</>");
            $this->line("");
            $this->warn("⚠️  WARNING: This downgrades members to free tier after grace period!");
            $this->line("");

            if ($sync || $dryRun) {
                // Run job immediately (sync mode or dry-run)
                $this->info("⏳ Running subscription processing synchronously...");
                app()->call([new ProcessExpiredSubscriptionsJob($graceDays, $dryRun), 'handle']);
                $this->info("✅ Subscription processing job completed");

                if ($dryRun) {
                    $this->line("<fg=yellow>Note: This was a dry-run. No subscriptions were downgraded.</>");
                }
            } else {
                // Queue job for async execution
                $this->info("📤 Dispatching subscription processing job to queue...");
                ProcessExpiredSubscriptionsJob::dispatch($graceDays, $dryRun);
                $this->info("✅ Job dispatched to queue for processing");
            }

            Log::info('ProcessExpiredSubscriptions command executed', [
                'grace_days' => $graceDays,
                'sync' => $sync,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('ProcessExpiredSubscriptions command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
