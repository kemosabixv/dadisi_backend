<?php

namespace App\Console\Commands;

use App\Jobs\RefreshExchangeRatesJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshExchangeRates extends Command
{
    protected $signature = 'exchange-rates:auto-refresh {--sync} {--dry-run}';

    protected $description = 'Automatically refresh exchange rates if cache has expired via admin API endpoint';

    /**
     * Execute the console command.
     * 
     * Checks if rates need refresh based on admin-configured cache duration and calls
     * the admin API endpoint to update from ExchangeRate-API.com if expired.
     * Can run immediately with --sync or preview with --dry-run.
     */
    public function handle(): int
    {
        try {
            $dryRun = $this->option('dry-run');
            $sync = $this->option('sync');

            $this->info("🐍 Refresh Exchange Rates Command");
            $this->info("{'━'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━}");
            $this->line("Sync Mode: <fg=yellow>" . ($sync ? 'Yes' : 'No [Queued]') . "</>");
            $this->line("Dry Run: <fg=yellow>" . ($dryRun ? 'Yes [Preview Mode]' : 'No [Will Refresh]') . "</>");
            $this->line("");

            if ($sync || $dryRun) {
                $this->info("⏳ Running exchange rate refresh synchronously...");
                app()->call([new RefreshExchangeRatesJob($dryRun), 'handle']);
                $this->info("✅ Exchange rates refresh job completed");

                if ($dryRun) {
                    $this->line("<fg=yellow>Note: This was a dry-run. No rates were refreshed.</>");
                }
            } else {
                $this->info("📤 Dispatching exchange rates refresh to queue...");
                RefreshExchangeRatesJob::dispatch($dryRun);
                $this->info("✅ Job dispatched to queue for processing");
            }

            Log::info('RefreshExchangeRates command executed', [
                'sync' => $sync,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('RefreshExchangeRates command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
