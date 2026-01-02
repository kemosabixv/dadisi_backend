<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'capacity' => $this->quantity,
            'is_sold_out' => $this->quantity !== null && $this->available <= 0,
            'available_until' => $this->available_until?->toIso8601String(),
            'is_active' => (bool) $this->is_active,
        ];
    }
}
