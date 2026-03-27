<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceLogResource extends JsonResource
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
            'booking_id' => $this->booking_id,
            'lab_id' => $this->lab_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'check_in_time' => $this->check_in_time?->toIso8601String(),
            'slot_start_time' => $this->slot_start_time?->toIso8601String(),
            'marked_by_id' => $this->marked_by_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Relations
            'user' => UserResource::make($this->whenLoaded('user')),
            'marked_by' => UserResource::make($this->whenLoaded('markedBy')),
        ];
    }
}
