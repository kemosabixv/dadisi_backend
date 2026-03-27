<?php

namespace App\Console\Commands;

use App\Jobs\SendDonationRemindersJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDonationReminders extends Command
{
    protected $signature = 'donations:send-reminders {--sync} {--dry-run} {--hours=24}';

    protected $description = 'Send reminder emails for pending donations (24h old by default)';

    /**
     * Execute the console command.
     * 
     * Sends reminders for pending donations older than --hours (default 24).
     * Can run immediately with --sync or preview with --dry-run.
     */
    public function handle()
    {
        try {
            $hours = (int) $this->option('hours');
            $dryRun = $this->option('dry-run');
            $sync = $this->option('sync');

            $this->info("💌 Send Donation Reminders Command");
            $this->info("{'━'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━}");
            $this->line("Hours Threshold: <fg=cyan>{$hours}</>");
            $this->line("Sync Mode: <fg=yellow>" . ($sync ? 'Yes' : 'No [Queued]') . "</>");
            $this->line("Dry Run: <fg=yellow>" . ($dryRun ? 'Yes [Preview Mode]' : 'No [Will Send]') . "</>");
            $this->line("");

            if ($sync || $dryRun) {
                $this->info("⏳ Running donation reminders synchronously...");
                app()->call([new SendDonationRemindersJob($hours, $dryRun), 'handle']);
                $this->info("✅ Donation reminders job completed");

                if ($dryRun) {
                    $this->line("<fg=yellow>Note: This was a dry-run. No reminders were sent.</>");
                }
            } else {
                $this->info("📤 Dispatching donation reminders job to queue...");
                SendDonationRemindersJob::dispatch($hours, $dryRun);
                $this->info("✅ Job dispatched to queue for processing");
            }

            Log::info('SendDonationReminders command executed', [
                'hours' => $hours,
                'sync' => $sync,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('SendDonationReminders command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
