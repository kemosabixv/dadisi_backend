<?php

namespace App\Console\Commands;

use App\Jobs\RunReconciliationJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Reconciliation\ReconciliationService;
use Illuminate\Support\Facades\Log;

class RunReconciliation extends Command
{
    protected $signature = 'reconciliation:run
                            {--dry-run : Run without persisting results}
                            {--sync : Run synchronously instead of queueing}
                            {--period-start= : Period start date (YYYY-MM-DD)}
                            {--period-end= : Period end date (YYYY-MM-DD)}
                            {--county= : County filter}
                            {--app-file= : Path to JSON file with app transactions}
                            {--gateway-file= : Path to JSON file with gateway transactions}';

    protected $description = 'Run a reconciliation between app and gateway transactions';

    /**
     * Execute the console command.
     * 
     * Reconciles app transactions with payment gateway transactions.
     * Can run immediately with --sync or preview with --dry-run.
     * Defaults to queue dispatch for async processing.
     */
    public function handle(ReconciliationService $service): int
    {
        try {
            $dryRun = $this->option('dry-run');
            $sync = $this->option('sync');

            $this->info("💰 Reconciliation Command");
            $this->info("{'━'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━}");
            $this->line("Sync Mode: <fg=yellow>" . ($sync ? 'Yes' : 'No [Queued]') . "</>");
            $this->line("Dry Run: <fg=yellow>" . ($dryRun ? 'Yes [Preview Mode]' : 'No [Will Reconcile]') . "</>");
            if ($this->option('period-start')) {
                $this->line("Period Start: <fg=cyan>" . $this->option('period-start') . "</>");
            }
            if ($this->option('period-end')) {
                $this->line("Period End: <fg=cyan>" . $this->option('period-end') . "</>");
            }
            if ($this->option('county')) {
                $this->line("County: <fg=cyan>" . $this->option('county') . "</>");
            }
            $this->line("");

            $appTransactions = [];
            $gatewayTransactions = [];

            $appFile = $this->option('app-file');
            $gatewayFile = $this->option('gateway-file');

            if ($appFile && file_exists($appFile)) {
                $content = file_get_contents($appFile);
                $appTransactions = json_decode($content, true) ?? [];
            }

            if ($gatewayFile && file_exists($gatewayFile)) {
                $content = file_get_contents($gatewayFile);
                $gatewayTransactions = json_decode($content, true) ?? [];
            }

            $options = [
                'period_start' => $this->option('period-start') ?: null,
                'period_end' => $this->option('period-end') ?: null,
                'county' => $this->option('county') ?: null,
            ];

            if ($dryRun) {
                $this->info("⏳ Running reconciliation in dry-run mode...");
                DB::beginTransaction();
                try {
                    $run = $service->runFromData($appTransactions, $gatewayTransactions, $options);
                    $this->info("✅ Dry-run completed");
                    $this->line("   • Run ID: {$run->run_id}");
                    $this->line("<fg=yellow>Note: This was a dry-run. No changes were persisted.</>");
                    DB::rollBack();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $this->error("❌ Dry-run failed: " . $e->getMessage());
                    Log::error('RunReconciliation dry-run failed', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    return self::FAILURE;
                }
                return self::SUCCESS;
            }

            if ($sync) {
                $this->info("⏳ Running reconciliation synchronously...");
                try {
                    $run = $service->runFromData($appTransactions, $gatewayTransactions, $options);
                    $this->info("✅ Reconciliation completed");
                    $this->line("   • Run ID: {$run->run_id}");
                } catch (\Throwable $e) {
                    $this->error("❌ Reconciliation failed: " . $e->getMessage());
                    Log::error('RunReconciliation sync failed', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    return self::FAILURE;
                }
                return self::SUCCESS;
            }

            // Dispatch to queue for asynchronous processing
            $this->info("📤 Dispatching reconciliation to queue...");
            RunReconciliationJob::dispatch($appTransactions, $gatewayTransactions, $options);
            $this->info("✅ Job dispatched to queue for processing");

            Log::info('RunReconciliation command executed', [
                'sync' => $sync,
                'dry_run' => $dryRun,
                'options' => $options,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('RunReconciliation command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
