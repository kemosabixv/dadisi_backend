<?php

namespace App\Console\Commands;

use App\Jobs\SendLabBookingRemindersJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendLabBookingReminders extends Command
{
    protected $signature = 'lab:send-booking-reminders {--sync} {--dry-run}';

    protected $description = 'Send reminder notifications for lab bookings starting tomorrow';

    /**
     * Execute the console command.
     * 
     * Sends reminders for lab bookings starting within 23-25 hours.
     * Can run immediately with --sync or preview with --dry-run.
     */
    public function handle(): int
    {
        try {
            $dryRun = $this->option('dry-run');
            $sync = $this->option('sync');

            $this->info("🧪 Send Lab Booking Reminders Command");
            $this->info("{'━'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━}");
            $this->line("Sync Mode: <fg=yellow>" . ($sync ? 'Yes' : 'No [Queued]') . "</>");
            $this->line("Dry Run: <fg=yellow>" . ($dryRun ? 'Yes [Preview Mode]' : 'No [Will Send]') . "</>");
            $this->line("");

            if ($sync || $dryRun) {
                $this->info("⏳ Running lab booking reminders synchronously...");
                app()->call([new SendLabBookingRemindersJob($dryRun), 'handle']);
                $this->info("✅ Lab booking reminders job completed");

                if ($dryRun) {
                    $this->line("<fg=yellow>Note: This was a dry-run. No reminders were sent.</>");
                }
            } else {
                $this->info("📤 Dispatching lab booking reminders job to queue...");
                SendLabBookingRemindersJob::dispatch($dryRun);
                $this->info("✅ Job dispatched to queue for processing");
            }

            Log::info('SendLabBookingReminders command executed', [
                'sync' => $sync,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('SendLabBookingReminders command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
