<?php

namespace App\Services;

use App\Models\ExchangeRate;


class CurrencyService
{
    /**
     * Convert KES to USD using database-stored rates
     */
    public function kesToUSD(float $kesAmount): float
    {
        $exchangeRate = ExchangeRate::getLatest();
        return round($kesAmount * $exchangeRate->getInverseRate(), 2);
    }

    /**
     * Convert USD to KES using database-stored rates
     */
    public function usdToKES(float $usdAmount): float
    {
        $exchangeRate = ExchangeRate::getLatest();
        return round($usdAmount * $exchangeRate->rate, 2);
    }

    /**
     * Get current exchange rate (USD to KES) from database
     */
    public function getCurrentUSDRate(): float
    {
        $exchangeRate = ExchangeRate::getLatest();
        return (float) $exchangeRate->rate;
    }

    /**
     * Get current exchange rate (KES to USD) from database
     */
    public function getCurrentKESRate(): float
    {
        $exchangeRate = ExchangeRate::getLatest();
        return (float) $exchangeRate->getInverseRate();
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
        return $this->formatAmount($amount, $currency);
    }

    /**
     * Get exchange rate information for display
     */
    public function getExchangeInfo(): array
    {
        $exchangeRate = ExchangeRate::getLatest();
        $usdRate = $this->getCurrentUSDRate();
        $kesRate = $this->getCurrentKESRate();

        return [
            'usd_to_kes_rate' => $usdRate,
            'kes_to_usd_rate' => $kesRate,
            'last_updated' => $exchangeRate->last_updated->toDateTimeString(),
            'next_auto_refresh' => $exchangeRate->next_auto_refresh?->toDateTimeString(),
            'cache_time_days' => $exchangeRate->cache_time_days,
            'cache_minutes' => $exchangeRate->cache_minutes,
            'source' => 'database',
            'api_source' => 'exchangerate-api.com',
        ];
    }

    /**
     * Refresh exchange rate from API (admin function)
     */
    public function refreshRateFromAPI(): array
    {
        $exchangeRate = ExchangeRate::getLatest();
        return $exchangeRate->refreshFromAPI();
    }
}
