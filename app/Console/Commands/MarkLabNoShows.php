<?php

namespace App\Console\Commands;

use App\Jobs\MarkLabNoShowsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkLabNoShows extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lab:mark-no-shows {--sync} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark lab bookings as no-show if not checked in after end time (triggers refunds)';

    /**
     * Execute the console command.
     * 
     * CRITICAL: This command triggers refund processing!
     * - Finds lab bookings that expired 15+ minutes ago with no check-in
     * - Marks them as no-show (refund triggered automatically)
     * 
     * Options:
     * --sync      Run immediately (for testing/debugging)
     * --dry-run   Preview what would happen without making changes
     */
    public function handle()
    {
        try {
            $dryRun = $this->option('dry-run');
            $sync = $this->option('sync');

            $this->info("⏰ Lab No-Shows Marking Command");
            $this->info("{'━'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━}");
            $this->line("Sync Mode: <fg=yellow>" . ($sync ? 'Yes' : 'No [Queued]') . "</>");
            $this->line("Dry Run: <fg=yellow>" . ($dryRun ? 'Yes [Preview Mode]' : 'No [Will Mark]') . "</>");
            $this->line("");
            $this->warn("⚠️  WARNING: This marks bookings as no-show and TRIGGERS REFUNDS!");
            $this->line("");

            if ($sync || $dryRun) {
                // Run job immediately (sync mode or dry-run)
                $this->info("⏳ Running no-show marking synchronously...");
                app()->call([new MarkLabNoShowsJob($dryRun), 'handle']);
                $this->info("✅ No-show marking job completed");

                if ($dryRun) {
                    $this->line("<fg=yellow>Note: This was a dry-run. No bookings were marked.</>");
                }
            } else {
                // Queue job for async execution
                $this->info("📤 Dispatching no-show marking job to queue...");
                MarkLabNoShowsJob::dispatch($dryRun);
                $this->info("✅ Job dispatched to queue for processing");
            }

            Log::info('MarkLabNoShows command executed', [
                'sync' => $sync,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('MarkLabNoShows command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
