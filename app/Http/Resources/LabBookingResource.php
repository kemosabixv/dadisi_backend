<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * LabBookingResource
 *
 * Transforms LabBooking model to JSON response format.
 */
class LabBookingResource extends JsonResource
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
            'lab_space_id' => $this->lab_space_id,
            'user_id' => $this->user_id,
            'title' => $this->title ?? 'Lab Booking',
            'purpose' => $this->purpose ?? null, // Renamed from description
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'duration_hours' => $this->duration_hours,
            'status' => $this->status ?? 'confirmed',
            'booking_reference' => $this->booking_reference,
            'reference' => $this->booking_reference, // Alias for frontend compatibility
            
            // Payer Info (Consolidated in Model Accessors)
            'payer_name' => $this->payer_name,
            'payer_email' => $this->payer_email,

            // Presence Info (Consolidated in Model Accessors)
            'is_present' => $this->is_present,
            'checked_in_by' => $this->checked_in_by,

            'admin_notes' => $this->admin_notes ?? null, // Renamed from notes
            'lab_space' => LabSpaceResource::make($this->whenLoaded('labSpace')),
            'user' => UserResource::make($this->whenLoaded('user')),
            'booking_series_id' => $this->booking_series_id,
            'is_cancellable' => $this->is_cancellable,
            'is_deadline_reached' => $this->is_deadline_reached,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
