<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForumThreadResource extends JsonResource
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
            'content' => $this->posts?->first()?->content,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'category_id' => $this->category_id,
            'category' => new ForumCategoryResource($this->whenLoaded('category')),
            'county_id' => $this->county_id,
            'county' => new CountyResource($this->whenLoaded('county')),
            'featured_image' => $this->featured_image,
            'is_pinned' => (bool) $this->is_pinned,
            'is_locked' => (bool) $this->is_locked,
            'views_count' => (int) $this->views_count,
            'posts_count' => (int) $this->posts_count,
            'tags' => ForumTagResource::collection($this->whenLoaded('tags')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
