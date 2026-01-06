<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DonationCampaignResource extends JsonResource
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
            'short_description' => $this->short_description,
            'goal_amount' => $this->goal_amount ? (float) $this->goal_amount : null,
            'minimum_amount' => $this->minimum_amount ? (float) $this->minimum_amount : null,
            'effective_minimum_amount' => $this->getEffectiveMinimumAmount(),
            'current_amount' => $this->current_amount,
            'progress_percentage' => $this->progress_percentage,
            'donor_count' => $this->donor_count,
            'is_goal_reached' => $this->is_goal_reached,
            'currency' => $this->currency,
            'hero_image_url' => $this->hero_image_url,
            'featured_media_id' => $this->media->firstWhere('pivot.role', 'featured')?->id,
            'featured_media' => $this->whenLoaded('featuredMedia', fn() => $this->featuredMedia ? [
                'id' => $this->featuredMedia->id,
                'file_name' => $this->featuredMedia->file_name,
                'file_path' => $this->featuredMedia->file_path,
                'url' => $this->featuredMedia->url,
                'original_url' => $this->featuredMedia->original_url,
                'mime_type' => $this->featuredMedia->mime_type,
                'size' => $this->featuredMedia->size,
            ] : null),
            'gallery_media_ids' => $this->media->where('pivot.role', 'gallery')->pluck('id'),
            'media' => $this->whenLoaded('media', fn() => $this->media->map(fn($m) => [
                'id' => $m->id,
                'file_name' => $m->file_name,
                'file_path' => $m->file_path,
                'url' => $m->url,
                'original_url' => $m->original_url,
                'mime_type' => $m->mime_type,
                'size' => $m->size,
            ])->values()),
            'status' => $this->status,
            'county' => $this->whenLoaded('county', fn() => [
                'id' => $this->county->id,
                'name' => $this->county->name,
            ]),
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->username ?? $this->creator->email,
            ]),
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
