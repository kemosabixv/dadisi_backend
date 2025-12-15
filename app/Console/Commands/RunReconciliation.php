<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Reconciliation\ReconciliationService;

class RunReconciliation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reconciliation:run
                            {--dry-run : Run without persisting results}
                            {--sync : Run synchronously instead of queueing}
                            {--period-start= : Period start date (YYYY-MM-DD)}
                            {--period-end= : Period end date (YYYY-MM-DD)}
                            {--county= : County filter}
                            {--app-file= : Path to JSON file with app transactions}
                            {--gateway-file= : Path to JSON file with gateway transactions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a reconciliation between app and gateway transactions';

    public function handle(ReconciliationService $service)
    {
        $this->info('Starting reconciliation run...');

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

        if ($this->option('dry-run')) {
            DB::beginTransaction();
            try {
                $run = $service->runFromData($appTransactions, $gatewayTransactions, $options);
                $this->info("Dry-run complete. Run id: {$run->run_id}");
                DB::rollBack();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error('Dry-run failed: ' . $e->getMessage());
                return 1;
            }

            return 0;
        }

        // Allow forcing synchronous execution via --sync
        if ($this->option('sync')) {
            try {
                $run = $service->runFromData($appTransactions, $gatewayTransactions, $options);
                $this->info("Run completed. Run id: {$run->run_id}");
            } catch (\Throwable $e) {
                $this->error('Run failed: ' . $e->getMessage());
                return 1;
            }

            return 0;
        }

        // Dispatch to queue for asynchronous processing
        try {
            \App\Jobs\RunReconciliationJob::dispatch($appTransactions, $gatewayTransactions, $options);
            $this->info('Reconciliation job dispatched to queue.');
        } catch (\Throwable $e) {
            $this->error('Failed to dispatch job: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
