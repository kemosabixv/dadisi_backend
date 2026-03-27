<?php

namespace App\Console\Commands;

use App\Jobs\ReplenishMonthlyQuotaJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReplenishMonthlyQuotaCommand extends Command
{
    protected $signature = 'quota:replenish-monthly {--sync} {--dry-run} {--force} {--user=}';

    protected $description = 'Replenish monthly lab quota for all users with active subscriptions. Runs daily at 00:15.';

    /**
     * Execute the console command.
     * 
     * Creates/replenishes monthly quota commitment for current month (idempotent).
     * Only processes users with active lab subscriptions.
     * Can run immediately with --sync or preview with --dry-run.
     */
    public function handle(): int
    {
        try {
            $dryRun = $this->option('dry-run');
            $sync = $this->option('sync');
            $force = $this->option('force');
            $specificUserId = $this->option('user');

            $this->info("📅 Replenish Monthly Quota Command");
            $this->info("{'━'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━}");
            $this->line("Sync Mode: <fg=yellow>" . ($sync ? 'Yes' : 'No [Queued]') . "</>");
            $this->line("Dry Run: <fg=yellow>" . ($dryRun ? 'Yes [Preview Mode]' : 'No [Will Replenish]') . "</>");
            $this->line("Force: <fg=yellow>" . ($force ? 'Yes' : 'No') . "</>");
            if ($specificUserId) {
                $this->line("User ID: <fg=cyan>{$specificUserId}</>");
            }
            $this->line("");

            if ($sync || $dryRun) {
                $this->info("⏳ Running monthly quota replenishment synchronously...");
                app()->call([new ReplenishMonthlyQuotaJob($dryRun, $force, $specificUserId ? (int)$specificUserId : null), 'handle']);
                $this->info("✅ Monthly quota replenishment job completed");

                if ($dryRun) {
                    $this->line("<fg=yellow>Note: This was a dry-run. No quotas were replenished.</>");
                }
            } else {
                $this->info("📤 Dispatching monthly quota replenishment to queue...");
                ReplenishMonthlyQuotaJob::dispatch($dryRun, $force, $specificUserId ? (int)$specificUserId : null);
                $this->info("✅ Job dispatched to queue for processing");
            }

            Log::info('ReplenishMonthlyQuotaCommand executed', [
                'sync' => $sync,
                'dry_run' => $dryRun,
                'force' => $force,
                'user_id' => $specificUserId,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('ReplenishMonthlyQuotaCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
