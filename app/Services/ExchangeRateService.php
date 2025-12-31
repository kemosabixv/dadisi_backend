<?php

namespace App\Services;

use App\Services\Contracts\ExchangeRateServiceContract;
use App\Models\ExchangeRate;
use App\Exceptions\BusinessLogicException;
use Illuminate\Support\Facades\Log;

/**
 * Exchange Rate Service
 *
 * Handles all exchange rate operations including fetching, caching,
 * manual updates, and API refresh triggering.
 *
 * @implements ExchangeRateServiceContract
 */
class ExchangeRateService implements ExchangeRateServiceContract
{
    /**
     * Refresh exchange rate from external API
     *
     * @return array Result with rate, inverse_rate, and last_updated
     * @throws BusinessLogicException
     */
    public function refreshFromAPI(): array
    {
        try {
            $exchangeRate = ExchangeRate::getLatest();
            $result = $exchangeRate->refreshFromAPI();

            Log::channel('payment')->info('Exchange rate refreshed from API', [
                'rate' => $result['rate'],
                'timestamp' => $result['last_updated'],
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::channel('payment')->error('Failed to refresh exchange rate from API', [
                'error' => $e->getMessage(),
            ]);

            throw new BusinessLogicException(
                'Failed to refresh exchange rate from API: ' . $e->getMessage(),
                'EXCHANGE_RATE_REFRESH_FAILED'
            );
        }
    }

    /**
     * Update cache settings for exchange rates
     *
     * @param int $cacheMinutes Cache duration in minutes
     * @return array Updated settings
     * @throws BusinessLogicException
     */
    public function updateCacheSettings(int $cacheMinutes): array
    {
        try {
            $exchangeRate = ExchangeRate::getLatest();
            $exchangeRate->update(['cache_minutes' => $cacheMinutes]);

            $days = intval($cacheMinutes / 1440);
            $dayLabels = [
                3 => '3-day',
                5 => '5-day',
                7 => '7-day',
            ];

            Log::channel('payment')->info('Exchange rate cache settings updated', [
                'cache_minutes' => $cacheMinutes,
                'cache_days' => $days,
            ]);

            return [
                'message' => "Cache settings updated to {$dayLabels[$days]} ({$cacheMinutes} minutes)",
                'cache_minutes' => $cacheMinutes,
                'cache_days' => $days,
            ];
        } catch (\Exception $e) {
            Log::channel('payment')->error('Failed to update cache settings', [
                'error' => $e->getMessage(),
            ]);

            throw new BusinessLogicException(
                'Failed to update cache settings: ' . $e->getMessage(),
                'CACHE_UPDATE_FAILED'
            );
        }
    }

    /**
     * Update manual exchange rate
     *
     * @param float $rate New exchange rate
     * @return array Updated rate information
     * @throws BusinessLogicException
     */
    public function updateManualRate(float $rate): array
    {
        try {
            $inverseRate = 1 / $rate;
            $exchangeRate = ExchangeRate::getLatest();
            
            $exchangeRate->update([
                'rate' => $rate,
                'inverse_rate' => $inverseRate,
                'last_updated' => now(),
            ]);

            Log::channel('payment')->info('Manual exchange rate updated', [
                'rate' => $rate,
                'inverse_rate' => $inverseRate,
            ]);

            return [
                'message' => 'Exchange rate manually updated',
                'rate' => $rate,
                'inverse_rate' => $inverseRate,
                'last_updated' => $exchangeRate->last_updated->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::channel('payment')->error('Failed to update manual rate', [
                'error' => $e->getMessage(),
            ]);

            throw new BusinessLogicException(
                'Failed to update manual rate: ' . $e->getMessage(),
                'MANUAL_RATE_UPDATE_FAILED'
            );
        }
    }

    /**
     * Get exchange rate information
     *
     * @return array Exchange rate info with cache and source details
     */
    public function getExchangeInfo(): array
    {
        $exchangeRate = ExchangeRate::getLatest();
        
        return [
            'rate' => (float) $exchangeRate->rate,
            'kes_to_usd_rate' => (float) $exchangeRate->inverse_rate,
            'usd_to_kes_rate' => (float) $exchangeRate->rate,
            'last_updated' => $exchangeRate->last_updated?->toIso8601String(),
            'next_auto_refresh' => $exchangeRate->last_updated
                ? $exchangeRate->last_updated->addMinutes($exchangeRate->cache_minutes)->toIso8601String()
                : null,
            'cache_time_days' => intval($exchangeRate->cache_minutes / 1440),
            'cache_minutes' => (int) $exchangeRate->cache_minutes,
            'source' => 'database',
            'api_source' => 'exchangerate-api.com',
        ];
    }

    /**
     * Convert amount from one currency to another
     *
     * @param float $amount Amount to convert
     * @param string $from Source currency code
     * @param string $to Target currency code
     * @return float Converted amount
     */
    public function convert(float $amount, string $from, string $to): float
    {
        if (strtoupper($from) === strtoupper($to)) {
            return $amount;
        }

        $exchangeRate = ExchangeRate::getLatest();

        if (strtoupper($from) === 'USD' && strtoupper($to) === 'KES') {
            return $amount * $exchangeRate->rate;
        }

        if (strtoupper($from) === 'KES' && strtoupper($to) === 'USD') {
            return $amount * $exchangeRate->inverse_rate;
        }

        throw new BusinessLogicException(
            "Unsupported currency conversion: {$from} to {$to}",
            'UNSUPPORTED_CONVERSION'
        );
    }

    /**
     * Get the current exchange rate
     *
     * @return float Exchange rate value
     */
    public function getRate(): float
    {
        return (float) ExchangeRate::getLatest()->rate;
    }

    /**
     * Get the inverse exchange rate
     *
     * @return float Inverse rate value
     */
    public function getInverseRate(): float
    {
        return (float) ExchangeRate::getLatest()->inverse_rate;
    }

    /**
     * Format amount with currency symbol
     */
    public function formatAmount(float $amount, string $currency = 'KES'): string
    {
        if ($currency === 'KES') {
            return 'KSh ' . number_format($amount, 0, '.', ',');
        } elseif ($currency === 'USD') {
            return '$' . number_format($amount, 2, '.', ',');
        }

        return number_format($amount, 2, '.', ',') . ' ' . $currency;
    }

    /**
     * Format amount for display with proper localization
     */
    public function formatMoney($amount, string $currency = 'KES'): string
    {
        return $this->formatAmount((float) $amount, $currency);
    }

    /**
     * Legacy helper: Convert KES to USD
     */
    public function kesToUSD(float $kesAmount): float
    {
        return $this->convert($kesAmount, 'KES', 'USD');
    }

    /**
     * Legacy helper: Convert USD to KES
     */
    public function usdToKES(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'USD', 'KES');
    }

    /**
     * Legacy helper: Get current USD rate
     */
    public function getCurrentUSDRate(): float
    {
        return $this->getRate();
    }

    /**
     * Legacy helper: Get current KES rate
     */
    public function getCurrentKESRate(): float
    {
        return $this->getInverseRate();
    }
}
