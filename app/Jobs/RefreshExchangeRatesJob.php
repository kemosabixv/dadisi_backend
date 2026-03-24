<?php

namespace App\Jobs;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshExchangeRatesJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 30;

    public function __construct(
        public bool $dryRun = false
    ) {}

    /**
     * Execute the job to refresh exchange rates.
     * 
     * Checks if exchange rates need refresh based on admin-configured cache duration
     * and calls the service to update from ExchangeRate-API.com if expired.
     */
    public function handle(\App\Services\Contracts\ExchangeRateServiceContract $exchangeRateService): void
    {
        Log::info('RefreshExchangeRatesJob started', ['dry_run' => $this->dryRun]);

        try {
            // Check if rates need refresh by looking at the database
            $exchangeRate = ExchangeRate::getLatest();

            // Check if refresh is needed
            if (!$exchangeRate->needsRefresh()) {
                $nextRefresh = $exchangeRate->next_auto_refresh?->format('Y-m-d H:i:s T') ?? 'Unknown';
                Log::info('Exchange rates still current', ['next_refresh' => $nextRefresh]);
                return;
            }

            // Rates need refresh - call the service directly
            Log::info('Exchange rates expired, attempting refresh via Service');

            if ($this->dryRun) {
                Log::info('Exchange rates refresh skipped (dry-run mode)');
                return;
            }

            $result = $exchangeRateService->refreshFromAPI();

            Log::info('Exchange rates refreshed successfully via Service', [
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('RefreshExchangeRatesJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
