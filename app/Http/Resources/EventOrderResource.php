<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventOrderResource extends JsonResource
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
            'user_id' => $this->user_id,
            'event_id' => $this->event_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'qr_code_token' => $this->qr_code_token,
            'qr_code_path' => $this->qr_code_path,
            'purchased_at' => $this->purchased_at ? $this->purchased_at->toIso8601String() : null,
            'checked_in_at' => $this->checked_in_at ? $this->checked_in_at->toIso8601String() : null,
            'guest_name' => $this->guest_name,
            'guest_email' => $this->guest_email,
            'guest_phone' => $this->guest_phone,
            'created_at' => $this->created_at->toIso8601String(),
            
            // Relationships
            'event' => new EventResource($this->whenLoaded('event')),
            'user' => new UserResource($this->whenLoaded('user')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
