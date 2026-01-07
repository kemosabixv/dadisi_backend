<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Exception;

class ExchangeRate extends Model
{
    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'inverse_rate',
        'cache_minutes',
        'last_updated',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'inverse_rate' => 'decimal:6',
        'cache_minutes' => 'integer',
        'last_updated' => 'datetime',
    ];

    /**
     * Get the current exchange rate
     */
    public function getCurrentRate()
    {
        return $this->rate;
    }

    /**
     * Get the inverse rate (KES to USD)
     */
    public function getInverseRate()
    {
        return $this->inverse_rate;
    }

    /**
     * Refresh rate from ExchangeRate-API.com
     */
    public function refreshFromAPI(): array
    {
        try {
            $apiKey = config('services.exchange_rate_api.key');
            if (!$apiKey) {
                throw new Exception('Exchange Rate API key not configured');
            }

            $baseUrl = config('services.exchange_rate_api.base_url', 'https://v6.exchangerate-api.com/v6/');

            // Request USD to KES pair conversion
            $response = Http::get("{$baseUrl}{$apiKey}/pair/USD/KES");

            if (!$response->successful()) {
                throw new Exception("API request failed with status {$response->status()}");
            }

            $data = $response->json();

            if ($data['result'] !== 'success' || !isset($data['conversion_rate'])) {
                throw new Exception('Invalid API response: ' . ($data['error-type'] ?? 'Unknown error'));
            }

            $rate = $data['conversion_rate'];
            $inverseRate = 1 / $rate;

            $this->update([
                'rate' => $rate,
                'inverse_rate' => $inverseRate,
                'last_updated' => now(),
            ]);

            return [
                'success' => true,
                'rate' => $rate,
                'inverse_rate' => $inverseRate,
                'last_updated' => now(),
            ];

        } catch (Exception $e) {
            throw new Exception('Failed to refresh exchange rate from API: ' . $e->getMessage());
        }
    }

    /**
     * Check if rate needs refreshing based on cache time
     */
    public function needsRefresh(): bool
    {
        if (!$this->last_updated) {
            return true;
        }

        return $this->last_updated->addMinutes($this->cache_minutes)->isPast();
    }

    /**
     * Get cache time in days
     */
    public function getCacheTimeDaysAttribute()
    {
        return intval($this->cache_minutes / 1440); // Convert minutes to days
    }

    /**
     * Get next auto-refresh timestamp
     */
    public function getNextAutoRefreshAttribute()
    {
        return $this->last_updated?->addMinutes($this->cache_minutes);
    }

    /**
     * Get or create the latest exchange rate record
     */
    public static function getLatest(): self
    {
        return self::latest()->first() ?? self::createDefault();
    }

    /**
     * Create default exchange rate if none exists
     */
    private static function createDefault(): self
    {
        return self::create([
            'from_currency' => 'USD',
            'to_currency' => 'KES',
            'rate' => 145.0,
            'inverse_rate' => 0.006897,
            'cache_minutes' => 10080, // 7 days
            'last_updated' => now()->subDays(1), // Force refresh soon
        ]);
    }
}
