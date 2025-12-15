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
            'content' => $this->body, // Fix: use 'body' instead of 'content'
            'status' => $this->status,
            'is_featured' => (bool) $this->is_featured,
            'views_count' => $this->views_count,
            'featured_image' => $this->getFeaturedImagePath(), // Add featured image
            'author' => [
                'id' => $this->author_id,
                'name' => $this->author?->name,
                'email' => $this->author?->email,
            ],
            'county' => [
                'id' => $this->county_id,
                'name' => $this->county?->name,
            ],
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
                'is_featured' => $this->getFeaturedMediaImage()?->id === $media->id, // Mark featured media
            ]),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
