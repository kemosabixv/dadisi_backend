<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlotHoldResource extends JsonResource
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
            'reference' => $this->reference,
            'lab_space_id' => $this->lab_space_id,
            'user_id' => $this->user_id,
            'guest_email' => $this->guest_email,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'payment_intent_id' => $this->payment_intent_id,
            'renewal_count' => $this->renewal_count,
            'series_id' => $this->series_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
