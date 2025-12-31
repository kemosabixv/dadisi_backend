<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateExchangeRateCacheRequest;
use App\Http\Requests\UpdateManualExchangeRateRequest;
use App\Http\Resources\ExchangeRateResource;
use App\Models\ExchangeRate;
use App\Services\Contracts\ExchangeRateServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin - Exchange Rates
 * @groupDescription Endpoints for managing the system's currency exchange rates (USD to KES). Supports caching configuration, manual overrides, and integration health checks.
 */
class ExchangeRateAdminController extends Controller
{
    /**
     * Create a new controller instance
     */
    public function __construct(private ExchangeRateServiceContract $exchangeRateService)
    {
    }

    /**
     * Get Active Exchange Rate
     *
     * Retrieves the currently active USD to KES exchange rate used by the system.
     * This rate is fetched from the local database cache to ensure performance and reduce external API costs.
     *
     * @authenticated
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "from_currency": "USD",
     *     "to_currency": "KES",
     *     "rate": 145.0,
     *     "cache_minutes": 10080,
     *     "last_updated": "2025-12-03T14:00:00Z",
     *     "inverse_rate": 0.006897
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('view', ExchangeRate::class);
        $rate = ExchangeRate::getLatest();
        
        return response()->json([
            'success' => true,
            'data' => new ExchangeRateResource($rate),
        ]);
    }

    /**
     * Get Exchange Rate Details (Stats)
     *
     * Provides comprehensive exchange rate information including both USD/KES and KES/USD rates,
     * cache expiry information, next auto-refresh timestamp, and current cache settings.
     * Used for the admin dashboard widget to show currency health.
     *
     * @authenticated
     * @response 200 {
     *   "data": {
     *     "rate": 145.67,
     *     "kes_to_usd_rate": 0.0067,
     *     "usd_to_kes_rate": 150.0,
     *     "last_updated": "2025-12-03T14:00:00Z",
     *     "next_auto_refresh": "2025-12-10T14:00:00Z",
     *     "cache_time_days": 7,
     *     "cache_minutes": 10080,
     *     "source": "database",
     *     "api_source": "exchangerate-api.com"
     *   }
     * }
     */
    public function show(Request $request): JsonResponse
    {
        $this->authorize('view', ExchangeRate::class);
        $info = $this->exchangeRateService->getExchangeInfo();
        
        return response()->json([
            'success' => true,
            'data' => $info,
        ]);
    }

    /**
     * Force Rate Refresh (API)
     *
     * Triggers an immediate call to the external currency API to fetch the latest exchange rates.
     * This overrides the local cache and resets the "last_updated" timestamp.
     * **Note:** This action consumes one unit of the external API's usage quota.
     *
     * @authenticated
     * @response 201 {
     *   "success": true,
     *   "message": "Exchange rate refreshed from API",
     *   "data": {
     *     "rate": 145.67,
     *     "inverse_rate": 0.006896,
     *     "last_updated": "2025-12-03T14:00:00Z"
     *   }
     * }
     * @response 422 {
     *   "success": false,
     *   "message": "Failed to refresh exchange rate from API",
     *   "error": "API connection failed"
     * }
     */
    public function refresh(Request $request): JsonResponse
    {
        $this->authorize('refreshFromApi', ExchangeRate::class);

        $result = $this->exchangeRateService->refreshFromAPI();

        return response()->json([
            'success' => true,
            'message' => 'Exchange rate refreshed from API',
            'data' => $result,
        ], 201);
    }

    /**
     * Configure Rate Caching
     *
     * Updates the duration (in minutes) that exchange rates remain valid before an auto-refresh is triggered.
     * Long cache durations save API costs, while shorter durations ensure tighter alignment with market rates.
     *
     * @authenticated
     * @bodyParam cache_minutes integer required The cache duration in minutes. Allowed values: 4320 (3 days), 7200 (5 days), 10080 (7 days). Example: 10080
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Cache settings updated to 7-day (10080 minutes)",
     *   "data": {
     *     "cache_minutes": 10080,
     *     "cache_days": 7
     *   }
     * }
     * @response 422 {
     *   "success": false,
     *   "message": "Invalid cache minutes value. Must be one of: 4320, 7200, 10080"
     * }
     */
    public function updateCache(UpdateExchangeRateCacheRequest $request): JsonResponse
    {
        $this->authorize('updateCacheSettings', ExchangeRate::class);

        $result = $this->exchangeRateService->updateCacheSettings($request->validated('cache_minutes'));

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'cache_minutes' => $result['cache_minutes'],
                'cache_days' => $result['cache_days'],
            ],
        ]);
    }

    /**
     * Set Manual Exchange Rate
     *
     * Manually overrides the system's exchange rate with a specific value.
     * This is useful for locking in a fixed rate for promotions or stabilizing pricing during high volatility.
     * The manual rate persists until the next manual update or API refresh.
     *
     * @authenticated
     * @bodyParam rate number required The custom USD to KES rate (Between 1 and 1000). Example: 145.50
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Exchange rate manually updated",
     *   "data": {
     *     "rate": 145.50,
     *     "inverse_rate": 0.006872,
     *     "last_updated": "2025-12-03T14:00:00Z"
     *   }
     * }
     * @response 422 {
     *   "success": false,
     *   "message": "The rate field is required. The rate must be a number between 1 and 1000."
     * }
     */
    public function updateManual(UpdateManualExchangeRateRequest $request): JsonResponse
    {
        $this->authorize('updateManualRate', ExchangeRate::class);

        $result = $this->exchangeRateService->updateManualRate($request->validated('rate'));

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'rate' => $result['rate'],
                'inverse_rate' => $result['inverse_rate'],
                'last_updated' => $result['last_updated'],
            ],
        ]);
    }
}
