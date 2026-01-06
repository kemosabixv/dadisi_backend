<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'content' => $this->body, // Alias for body
            'status' => $this->status,
            'is_featured' => (bool) $this->is_featured,
            'views_count' => $this->views_count,
            'featured_image' => $this->getFeaturedImagePath(),
            'hero_image_path' => $this->getFeaturedImagePath(),
            'author' => [
                'id' => $this->author_id,
                'username' => $this->author?->username,
                'name' => $this->author?->name,
                'email' => $this->author?->email,
            ],
            'county_id' => $this->county_id,
            'county' => $this->county ? [
                'id' => $this->county->id,
                'name' => $this->county->name,
            ] : null,
            'categories' => $this->categories->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
            ]),
            'tags' => $this->tags->map(fn($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ]),
            'media' => $this->media->map(fn($media) => [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'file_path' => $media->file_path,
                'type' => $media->type,
                'mime_type' => $media->mime_type,
                'file_size' => $media->file_size,
                'url' => $media->url,
                'is_featured' => $this->getFeaturedMediaImage()?->id === $media->id,
            ]),
            'featured_media' => $this->whenLoaded('media', function () {
                $featured = $this->media->firstWhere('pivot.role', 'featured');
                if ($featured) {
                    return [
                        'id' => $featured->id,
                        'file_name' => $featured->file_name,
                        'file_path' => $featured->file_path,
                        'url' => $featured->url,
                        'mime_type' => $featured->mime_type,
                        'file_size' => $featured->file_size,
                    ];
                }
                return null;
            }),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'user_id' => $this->user_id,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
