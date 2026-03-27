<?php

namespace App\Console\Commands;

use App\Jobs\SendSubscriptionRemindersJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendSubscriptionReminders extends Command
{
    protected $signature = 'subscriptions:send-reminders {--sync} {--dry-run}';

    protected $description = 'Send renewal reminders for subscriptions nearing expiration';

    /**
     * Execute the console command.
     * 
     * Sends reminders for active subscriptions approaching expiration.
     * Can run immediately with --sync or preview with --dry-run.
     */
    public function handle()
    {
        try {
            $dryRun = $this->option('dry-run');
            $sync = $this->option('sync');

            $this->info("📧 Send Subscription Reminders Command");
            $this->info("{'━'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━}");
            $this->line("Sync Mode: <fg=yellow>" . ($sync ? 'Yes' : 'No [Queued]') . "</>");
            $this->line("Dry Run: <fg=yellow>" . ($dryRun ? 'Yes [Preview Mode]' : 'No [Will Send]') . "</>");
            $this->line("");

            if ($sync || $dryRun) {
                $this->info("⏳ Running subscription reminders synchronously...");
                app()->call([new SendSubscriptionRemindersJob($dryRun), 'handle']);
                $this->info("✅ Subscription reminders job completed");

                if ($dryRun) {
                    $this->line("<fg=yellow>Note: This was a dry-run. No reminders were sent.</>");
                }
            } else {
                $this->info("📤 Dispatching subscription reminders job to queue...");
                SendSubscriptionRemindersJob::dispatch($dryRun);
                $this->info("✅ Job dispatched to queue for processing");
            }

            Log::info('SendSubscriptionReminders command executed', [
                'sync' => $sync,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('SendSubscriptionReminders command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
