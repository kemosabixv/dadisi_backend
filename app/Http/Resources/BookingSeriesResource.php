<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingSeriesResource extends JsonResource
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
            'user_id' => $this->user_id,
            'lab_space_id' => $this->lab_space_id,
            'reference' => $this->reference,
            'type' => $this->type,
            'status' => $this->status,
            'total_hours' => (float) $this->total_hours,
            'metadata' => $this->metadata,
            'bookings' => LabBookingResource::collection($this->whenLoaded('bookings')),
            'holds' => SlotHoldResource::collection($this->whenLoaded('holds')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
