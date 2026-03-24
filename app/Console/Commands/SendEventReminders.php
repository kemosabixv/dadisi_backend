<?php

namespace App\Console\Commands;

use App\Jobs\SendEventRemindersJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders {--sync} {--dry-run}';

    protected $description = 'Send 24h and 1h reminders to event attendees';

    /**
     * Execute the console command.
     * 
     * Sends 24-hour and 1-hour reminders to event registrations and orders.
     * Can run immediately with --sync or preview with --dry-run.
     */
    public function handle(): int
    {
        try {
            $dryRun = $this->option('dry-run');
            $sync = $this->option('sync');

            $this->info("📅 Send Event Reminders Command");
            $this->info("{'━'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━}");
            $this->line("Sync Mode: <fg=yellow>" . ($sync ? 'Yes' : 'No [Queued]') . "</>");
            $this->line("Dry Run: <fg=yellow>" . ($dryRun ? 'Yes [Preview Mode]' : 'No [Will Send]') . "</>");
            $this->line("");

            if ($sync || $dryRun) {
                $this->info("⏳ Running event reminders synchronously...");
                app()->call([new SendEventRemindersJob($dryRun), 'handle']);
                $this->info("✅ Event reminders job completed");

                if ($dryRun) {
                    $this->line("<fg=yellow>Note: This was a dry-run. No reminders were sent.</>");
                }
            } else {
                $this->info("📤 Dispatching event reminders job to queue...");
                SendEventRemindersJob::dispatch($dryRun);
                $this->info("✅ Job dispatched to queue for processing");
            }

            Log::info('SendEventReminders command executed', [
                'sync' => $sync,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('SendEventReminders command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
