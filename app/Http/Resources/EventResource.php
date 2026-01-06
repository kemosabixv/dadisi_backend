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
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }),
            'venue' => $this->venue,
            'is_online' => (bool) $this->is_online,
            'online_link' => $this->online_link,
            'capacity' => $this->capacity,
            'waitlist_enabled' => (bool) $this->waitlist_enabled,
            'waitlist_capacity' => $this->waitlist_capacity,
            'county_id' => $this->county_id,
            'county' => $this->whenLoaded('county', function () {
                return [
                    'id' => $this->county->id,
                    'name' => $this->county->name,
                ];
            }),
            'image_path' => $this->image_path,
            'image_url' => $this->image_path ? url('storage/' . $this->image_path) : null,
            'featured_media_id' => $this->media->firstWhere('pivot.role', 'featured')?->id,
            'featured_media' => $this->whenLoaded('media', function () {
                $featured = $this->media->firstWhere('pivot.role', 'featured');
                return $featured ? [
                    'id' => $featured->id,
                    'file_name' => $featured->file_name,
                    'file_path' => $featured->file_path,
                    'url' => $featured->url,
                    'original_url' => $featured->original_url,
                    'mime_type' => $featured->mime_type,
                    'size' => $featured->size,
                ] : null;
            }),
            'gallery_media_ids' => $this->media->where('pivot.role', 'gallery')->pluck('id'),
            'gallery_media' => $this->whenLoaded('media', function () {
                return $this->media->where('pivot.role', 'gallery')->map(function ($m) {
                    return [
                        'id' => $m->id,
                        'file_name' => $m->file_name,
                        'url' => $m->url,
                    ];
                })->values();
            }),
            'media' => $this->whenLoaded('media', function () {
                return $this->media->map(function ($m) {
                    return [
                        'id' => $m->id,
                        'file_name' => $m->file_name,
                        'file_path' => $m->file_path,
                        'url' => $m->url,
                        'original_url' => $m->original_url,
                        'mime_type' => $m->mime_type,
                        'size' => $m->size,
                    ];
                })->values();
            }),
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'status' => $this->status,
            'featured' => (bool) $this->featured,
            'featured_until' => $this->featured_until,
            'registration_deadline' => $this->registration_deadline,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
            'speakers' => SpeakerResource::collection($this->whenLoaded('speakers')),
            'tags' => EventTagResource::collection($this->whenLoaded('tags')),
            'registrations_count' => $this->when(isset($this->registrations_count), $this->registrations_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
