<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * LabSpaceResource
 *
 * Transforms LabSpace model to JSON response format.
 */
class LabSpaceResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type ?? null,
            'description' => $this->description ?? null,
            'capacity' => $this->capacity ?? 4,
            'county' => $this->county ?? null,
            'location' => $this->location ?? null,
            'equipment_list' => $this->equipment_list ?? [],
            'safety_requirements' => $this->safety_requirements ?? [],
            'opens_at' => $this->opens_at ? $this->opens_at->format('H:i') : null,
            'closes_at' => $this->closes_at ? $this->closes_at->format('H:i') : null,
            'operating_days' => $this->operating_days ?? [],
            'is_available' => (bool) $this->is_available,
            'bookings_enabled' => (bool) ($this->bookings_enabled ?? true),
            'hourly_rate' => (float) ($this->hourly_rate ?? 0),
            'computed_status' => $this->computed_status,
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'active_bookings_count' => $this->when(isset($this->active_bookings_count), $this->active_bookings_count),
            'bookings_count' => $this->when(isset($this->bookings_count), $this->bookings_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
