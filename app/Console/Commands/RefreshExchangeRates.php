<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange-rates:auto-refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically refresh exchange rates if cache has expired. Checks if rates need refresh based on admin-configured cache duration and calls the admin API endpoint to update from ExchangeRate-API.com if expired.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🐍 Checking exchange rate cache status...');

        try {
            // First check if rates need refresh by looking at the database
            $exchangeRate = ExchangeRate::getLatest();

            // Check if refresh is needed
            if (!$exchangeRate->needsRefresh()) {
                $nextRefresh = $exchangeRate->next_auto_refresh?->format('Y-m-d H:i:s T') ?? 'Unknown';
                $this->info("✅ Exchange rates are still current. Next auto-refresh: {$nextRefresh}");
                Log::info('Exchange rate auto-refresh skipped - rates still current');
                return 0;
            }

            // Rates need refresh - call the admin API endpoint
            $this->info('⏳ Exchange rates have expired. Calling refresh API endpoint...');

            // Find a super_admin user for authentication
            $adminUser = User::role('super_admin')->first();

            if (!$adminUser) {
                $this->error('❌ No super_admin user found for authentication');
                Log::error('Exchange rate auto-refresh failed: No super_admin user available');
                return 1;
            }

            // Create a personal access token for the admin user
            $token = $adminUser->createToken('auto-refresh-token', ['*'])->plainTextToken;

            // Make HTTP call to the admin refresh endpoint
            $baseUrl = config('app.url');
            $response = Http::withToken($token)->post("{$baseUrl}/api/admin/exchange-rates/refresh");

            if ($response->successful()) {
                $data = $response->json();

                $this->info("✅ Exchange rates refreshed successfully via API!");
                $this->line("   • Rate: {$data['rate']} USD to KES");
                $this->line("   • Last Updated: {$data['last_updated']}");

                Log::info('Exchange rates auto-refreshed successfully via API', [
                    'response_data' => $data,
                    'admin_user' => $adminUser->email,
                ]);
            } else {
                $this->error('❌ Failed to refresh exchange rates via API');
                $this->line("   • Status: {$response->status()}");
                $this->line("   • Response: " . $response->body());

                Log::error('Exchange rate auto-refresh failed via API', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'admin_user' => $adminUser->email,
                ]);

                return 1;
            }

        } catch (\Exception $e) {
            $this->error('❌ Exchange rate refresh command failed: ' . $e->getMessage());
            Log::error('Exchange rate auto-refresh command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }

        return 0;
    }
}
