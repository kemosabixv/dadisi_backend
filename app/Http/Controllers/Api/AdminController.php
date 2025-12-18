<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Services\CurrencyService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['getExchangeRate']); // Authenticate users, Policy handles authorization
    }

    /**
     * Get Active Exchange Rate
     *
     * Retrieves the currently active USD to KES exchange rate used by the system.
     * This rate is fetched from the local database cache to ensure performance and reduce external API costs.
     *
     * @group Admin - Exchange Rates
     * @groupDescription Endpoints for managing the system's currency exchange rates (USD to KES). Supports caching configuration, manual overrides, and integration health checks.
     * @authenticated
     * @response 200 {
     *   "id": 1,
     *   "from_currency": "USD",
     *   "to_currency": "KES",
     *   "rate": 145.0,
     *   "cache_minutes": 10080,
     *   "last_updated": "2025-12-03T14:00:00Z",
     *   "inverse_rate": 0.006897
     * }
     */
    public function getExchangeRate()
    {
        $this->authorize('view', ExchangeRate::class);
        $rate = ExchangeRate::getLatest();
        return response()->json($rate);
    }

    /**
     * Get Exchange Rate Details (Stats)
     *
     * Provides comprehensive exchange rate information including both USD/KES and KES/USD rates,
     * cache expiry information, next auto-refresh timestamp, and current cache settings.
     * Used for the admin dashboard widget to show currency health.
     *
     * @group Admin - Exchange Rates
     * @authenticated
     * @response 200 {
     *   "rate": 145.67,
     *   "kes_to_usd_rate": 0.0067,
     *   "usd_to_kes_rate": 150.0,
     *   "last_updated": "2025-12-03T14:00:00Z",
     *   "next_auto_refresh": "2025-12-10T14:00:00Z",
     *   "cache_time_days": 7,
     *   "cache_minutes": 10080,
     *   "source": "database",
     *   "api_source": "exchangerate-api.com"
     * }
     */
    public function getExchangeRateInfo(CurrencyService $currencyService)
    {
        $this->authorize('viewInfo', ExchangeRate::class);
        $info = $currencyService->getExchangeInfo();
        return response()->json($info);
    }

    /**
     * Force Rate Refresh (API)
     *
     * Triggers an immediate call to the external currency API to fetch the latest exchange rates.
     * This overrides the local cache and resets the "last_updated" timestamp.
     * **Note:** This action consumes one unit of the external API's usage quota.
     *
     * @group Admin - Exchange Rates
     * @authenticated
     *
     * @response 201 {
     *   "message": "Exchange rate refreshed from API",
     *   "rate": 145.67,
     *   "inverse_rate": 0.006896,
     *   "last_updated": "2025-12-03T14:00:00Z"
     * }
     * @response 422 {
     *   "message": "Failed to refresh exchange rate from API",
     *   "error": "API connection failed"
     * }
     */
    public function refreshExchangeRate()
    {
        $this->authorize('refreshFromApi', ExchangeRate::class);

        try {
            $exchangeRate = ExchangeRate::getLatest();
            $result = $exchangeRate->refreshFromAPI();

            return response()->json([
                'message' => 'Exchange rate refreshed from API',
                'rate' => $result['rate'],
                'inverse_rate' => $result['inverse_rate'],
                'last_updated' => $result['last_updated'],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to refresh exchange rate from API: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Configure Rate Caching
     *
     * Updates the duration (in minutes) that exchange rates remain valid before an auto-refresh is triggered.
     * Long cache durations save API costs, while shorter durations ensure tighter alignment with market rates.
     *
     * @group Admin - Exchange Rates
     * @authenticated
     *
     * @bodyParam cache_minutes integer required The cache duration in minutes. Allowed values: 4320 (3 days), 7200 (5 days), 10080 (7 days). Example: 10080
     *
     * @response 200 {
     *   "message": "Cache settings updated to 7-day (10080 minutes)",
     *   "cache_minutes": 10080,
     *   "cache_days": 7
     * }
     * @response 422 {
     *   "message": "Invalid cache minutes value. Must be one of: 4320, 7200, 10080"
     * }
     */
    public function updateCacheSettings(Request $request)
    {
        $this->authorize('updateCacheSettings', ExchangeRate::class);

        $validated = $request->validate([
            'cache_minutes' => 'required|integer|in:4320,7200,10080', // 3, 5, 7 days
        ]);

        $minutes = $validated['cache_minutes'];
        $days = ['4320' => '3-day', '7200' => '5-day', '10080' => '7-day'][$minutes . ''];

        try {
            $exchangeRate = ExchangeRate::getLatest();
            $exchangeRate->update(['cache_minutes' => $minutes]);

            return response()->json([
                'message' => "Cache settings updated to {$days} ({$minutes} minutes)",
                'cache_minutes' => $minutes,
                'cache_days' => intval($minutes / 1440), // Convert to days
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update cache settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set Manual Exchange Rate
     *
     * Manually overrides the system's exchange rate with a specific value.
     * This is useful for locking in a fixed rate for promotions or stabilizing pricing during high volatility.
     * The manual rate persists until the next manual update or API refresh.
     *
     * @group Admin - Exchange Rates
     * @authenticated
     *
     * @bodyParam rate number required The custom USD to KES rate (Between 1 and 1000). Example: 145.50
     *
     * @response 200 {
     *   "message": "Exchange rate manually updated",
     *   "rate": 145.50,
     *   "inverse_rate": 0.006872,
     *   "last_updated": "2025-12-03T14:00:00Z"
     * }
     * @response 422 {
     *   "message": "The rate field is required. The rate must be a number between 1 and 1000."
     * }
     */
    public function updateManualRate(Request $request)
    {
        $this->authorize('updateManualRate', ExchangeRate::class);

        $validated = $request->validate([
            'rate' => 'required|numeric|min:1|max:1000', // Reasonable range for KES/USD
        ]);

        $rate = $validated['rate'];
        $inverseRate = 1 / $rate;

        try {
            $exchangeRate = ExchangeRate::getLatest();
            $exchangeRate->update([
                'rate' => $rate,
                'inverse_rate' => $inverseRate,
                'last_updated' => now(),
            ]);

            return response()->json([
                'message' => 'Exchange rate manually updated',
                'rate' => $rate,
                'inverse_rate' => $inverseRate,
                'last_updated' => now(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update manual rate: ' . $e->getMessage(),
            ], 500);
        }
    }
}
