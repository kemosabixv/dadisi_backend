<?php

namespace App\Services\Contracts;

/**
 * Contract for Currency Service
 *
 * Handles exchange rate calculations and currency conversions.
 */
interface ExchangeRateServiceContract
{
    /**
     * Get current exchange rate information
     *
     * @return array{rate: float, kes_to_usd_rate: float, usd_to_kes_rate: float, last_updated: string, next_auto_refresh: string, cache_time_days: int, cache_minutes: int, source: string, api_source: string}
     */
    public function getExchangeInfo(): array;

    /**
     * Convert amount from one currency to another
     *
     * @param float $amount
     * @param string $from Currency code (e.g., 'USD')
     * @param string $to Currency code (e.g., 'KES')
     * @return float Converted amount
     */
    public function convert(float $amount, string $from, string $to): float;

    /**
     * Get the current exchange rate
     *
     * @return float Exchange rate value
     */
    public function getRate(): float;

    /**
     * Get the inverse exchange rate
     *
     * @return float Inverse rate (e.g., KES to USD when primary is USD to KES)
     */
    public function getInverseRate(): float;

    /**
     * Refresh exchange rate from external API
     *
     * @return array
     */
    public function refreshFromAPI(): array;

    /**
     * Update cache settings for exchange rates
     *
     * @param int $cacheMinutes
     * @return array
     */
    public function updateCacheSettings(int $cacheMinutes): array;

    /**
     * Update manual exchange rate
     *
     * @param float $rate
     * @return array
     */
    public function updateManualRate(float $rate): array;

    /**
     * Format amount with currency symbol
     *
     * @param float $amount
     * @param string $currency
     * @return string
     */
    public function formatAmount(float $amount, string $currency = 'KES'): string;

    /**
     * Format amount for display with proper localization
     *
     * @param mixed $amount
     * @param string $currency
     * @return string
     */
    public function formatMoney($amount, string $currency = 'KES'): string;

    /**
     * Legacy helper: Convert KES to USD
     * @param float $kesAmount
     * @return float
     */
    public function kesToUSD(float $kesAmount): float;

    /**
     * Legacy helper: Convert USD to KES
     * @param float $usdAmount
     * @return float
     */
    public function usdToKES(float $usdAmount): float;

    /**
     * Legacy helper: Get current USD rate
     * @return float
     */
    public function getCurrentUSDRate(): float;

    /**
     * Legacy helper: Get current KES rate
     * @return float
     */
    public function getCurrentKESRate(): float;
}
