<?php

namespace App\Console\Commands;

use App\Jobs\MarkLabAttendanceJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkLabAttendance extends Command
{
    protected $signature = 'lab:mark-attendance {--sync} {--dry-run}';

    protected $description = 'Finalize lab booking attendance 15 minutes after slot completion.';

    /**
     * Execute the console command.
     * 
     * Marks confirmed bookings as completed 15 minutes after their end time.
     * This locks bookings against refunds per PRD requirements.
     * Can run immediately with --sync or preview with --dry-run.
     */
    public function handle(): int
    {
        try {
            $dryRun = $this->option('dry-run');
            $sync = $this->option('sync');

            $this->info("🧪 Mark Lab Attendance Command");
            $this->info("{'━'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━}");
            $this->line("Sync Mode: <fg=yellow>" . ($sync ? 'Yes' : 'No [Queued]') . "</>");
            $this->line("Dry Run: <fg=yellow>" . ($dryRun ? 'Yes [Preview Mode]' : 'No [Will Mark]') . "</>");
            $this->line("");

            if ($sync || $dryRun) {
                $this->info("⏳ Running lab attendance marking synchronously...");
                app()->call([new MarkLabAttendanceJob($dryRun), 'handle']);
                $this->info("✅ Lab attendance marking job completed");

                if ($dryRun) {
                    $this->line("<fg=yellow>Note: This was a dry-run. No bookings were marked.</>");
                }
            } else {
                $this->info("📤 Dispatching lab attendance marking to queue...");
                MarkLabAttendanceJob::dispatch($dryRun);
                $this->info("✅ Job dispatched to queue for processing");
            }

            Log::info('MarkLabAttendance command executed', [
                'sync' => $sync,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('MarkLabAttendance command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
