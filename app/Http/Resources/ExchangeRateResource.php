<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Exchange Rate Resource
 *
 * Transforms exchange rate model into API response format.
 * Used for serializing exchange rate data in all endpoints.
 */
class ExchangeRateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_currency' => $this->from_currency,
            'to_currency' => $this->to_currency,
            'rate' => (float) $this->rate,
            'inverse_rate' => (float) $this->inverse_rate,
            'cache_minutes' => (int) $this->cache_minutes,
            'last_updated' => $this->last_updated?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
