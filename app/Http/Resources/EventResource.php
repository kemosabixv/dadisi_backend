<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => new EventCategoryResource($this->whenLoaded('category')),
            'venue' => $this->venue,
            'is_online' => (bool) $this->is_online,
            'online_link' => $this->online_link,
            'capacity' => $this->capacity,
            'waitlist_enabled' => (bool) $this->waitlist_enabled,
            'waitlist_capacity' => $this->waitlist_capacity,
            'county_id' => $this->county_id,
            'county' => $this->whenLoaded('county'),
            'image_path' => $this->image_path,
            'image_url' => $this->image_path ? url('storage/' . $this->image_path) : null,
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'status' => $this->status,
            'event_type' => $this->event_type,
            'featured' => (bool) $this->featured,
            'featured_until' => $this->featured_until,
            'registration_deadline' => $this->registration_deadline,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'organizer' => new UserResource($this->whenLoaded('organizer')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
            'speakers' => SpeakerResource::collection($this->whenLoaded('speakers')),
            'tags' => EventTagResource::collection($this->whenLoaded('tags')),
            'payouts' => PayoutResource::collection($this->whenLoaded('payouts')),
            'registrations_count' => $this->when(isset($this->registrations_count), $this->registrations_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
