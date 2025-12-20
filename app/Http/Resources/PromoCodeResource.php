<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event_title' => $this->whenLoaded('event', fn() => $this->event->title),
            'code' => $this->code,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'usage_limit' => $this->usage_limit,
            'used_count' => $this->used_count,
            'valid_from' => $this->valid_from,
            'valid_until' => $this->valid_until,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
