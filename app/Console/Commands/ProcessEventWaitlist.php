<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEventWaitlistJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessEventWaitlist extends Command
{
    protected $signature = 'app:process-event-waitlist {--sync} {--dry-run}';

    protected $description = 'Process event waitlists and promote users when capacity opens up.';

    /**
     * Execute the console command.
     * 
     * For all published events with capacity limits, promotes waiting users to confirmed
     * registrations as space becomes available.
     * Can run immediately with --sync or preview with --dry-run.
     */
    public function handle(): int
    {
        try {
            $dryRun = $this->option('dry-run');
            $sync = $this->option('sync');

            $this->info("📋 Process Event Waitlist Command");
            $this->info("{'━'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━}");
            $this->line("Sync Mode: <fg=yellow>" . ($sync ? 'Yes' : 'No [Queued]') . "</>");
            $this->line("Dry Run: <fg=yellow>" . ($dryRun ? 'Yes [Preview Mode]' : 'No [Will Promote]') . "</>");
            $this->line("");

            if ($sync || $dryRun) {
                $this->info("⏳ Running event waitlist processing synchronously...");
                app()->call([new ProcessEventWaitlistJob($dryRun), 'handle']);
                $this->info("✅ Event waitlist processing job completed");

                if ($dryRun) {
                    $this->line("<fg=yellow>Note: This was a dry-run. No users were promoted.</>");
                }
            } else {
                $this->info("📤 Dispatching event waitlist processing to queue...");
                ProcessEventWaitlistJob::dispatch($dryRun);
                $this->info("✅ Job dispatched to queue for processing");
            }

            Log::info('ProcessEventWaitlist command executed', [
                'sync' => $sync,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('ProcessEventWaitlist command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
