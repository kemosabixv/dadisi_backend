<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'county_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'status',
        'published_at',
        'meta_title',
        'meta_description',
        'is_featured',
        'views_count',
        'author_id',
        'content',
        'category',
        'allow_comments',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'county_id' => 'integer',
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'allow_comments' => 'boolean',
        'views_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'featured_image',
        'content',
        'is_published',
        'likes_count',
        'dislikes_count',
        'comments_count',
        'gallery_images',
    ];

    protected $attributes = [
        'status' => 'draft',
        'is_featured' => false,
    ];

    /**
     * Author relationship
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * User relationship alias (for factory compatibility)
     */
    public function user(): BelongsTo
    {
        return $this->author();
    }

    /**
     * Backwards-compatible alias: allow setting 'author_id' in tests/factories
     * to map to our 'user_id' column. Also auto-generate slug from title if title is set.
     */
    public function setAttribute($key, $value)
    {
        if ($key === 'author_id') {
            $key = 'user_id';
        }

        if ($key === 'content') {
            $key = 'body';
        }

        // Intercept 'category' to prevent SQL errors
        if ($key === 'category') {
            return $this;
        }

        parent::setAttribute($key, $value);

        // Auto-generate slug from title when title is set and slug is empty
        if ($key === 'title' && ! empty($value) && empty($this->getAttribute('slug'))) {
            $this->attributes['slug'] = \Illuminate\Support\Str::slug($value);
        }

        return $this;
    }

    /**
     * County relationship
     */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /**
     * Categories relationship
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'post_category');
    }

    /**
     * Tags relationship
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }

    /**
     * Comments relationship (polymorphic)
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Likes relationship (polymorphic)
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /**
     * Media relationship (attached images via polymorphic pivot)
     * Uses media_attachments pivot table for many-to-many polymorphic relationship
     */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'attachable', 'media_attachments')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the featured image media (role = 'featured')
     */
    public function featuredMedia()
    {
        return $this->media()->wherePivot('role', 'featured')->first();
    }

    /**
     * Get gallery images (role = 'gallery')
     */
    public function galleryMedia()
    {
        return $this->media()->wherePivot('role', 'gallery');
    }

    /**
     * Attach media as featured image (replaces existing featured)
     */
    public function setFeaturedMedia(int $mediaId): void
    {
        $old = $this->featuredMedia();
        if ($old) {
            $old?->decrement('usage_count');
        }

        $this->media()->wherePivot('role', 'featured')->detach();
        $this->media()->attach($mediaId, ['role' => 'featured']);
        Media::find($mediaId)?->increment('usage_count');
    }

    /**
     * Attach media as gallery images
     */
    public function addGalleryMedia(array $mediaIds): void
    {
        foreach ($mediaIds as $id) {
            if (! $this->media()->wherePivot('role', 'gallery')->where('media_id', $id)->exists()) {
                $this->media()->attach($id, ['role' => 'gallery']);
                Media::find($id)?->increment('usage_count');
            }
        }
    }

    protected static function boot()
    {
        parent::boot();
        static::deleting(function (self $post) {
            foreach ($post->media as $media) {
                $media?->decrement('usage_count');
            }
            $post->media()->detach();
        });
    }

    /**
     * Scope: published posts only
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    /**
     * Scope: draft posts
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope: featured posts
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: by county
     */
    public function scopeByCounty($query, $countyId)
    {
        return $query->where('county_id', $countyId);
    }

    /**
     * Scope: by category
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->whereHas('categories', function ($q) use ($categoryId) {
            $q->where('category_id', $categoryId);
        });
    }

    /**
     * Scope: by tag
     */
    public function scopeByTag($query, $tagId)
    {
        return $query->whereHas('tags', function ($q) use ($tagId) {
            $q->where('tag_id', $tagId);
        });
    }

    /**
     * Scope: search by title, excerpt, or body
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%")
                ->orWhere('body', 'like', "%{$term}%");
        });
    }

    /**
     * Scope: latest posts
     */
    public function scopeLatest($query, $limit = 10)
    {
        return $query->published()
            ->orderByDesc('published_at')
            ->limit($limit);
    }

    /**
     * Accessor: featured_image (CAS/R2 only)
     */
    public function getFeaturedImageAttribute(): ?string
    {
        $media = $this->getFeaturedMediaImage();

        return $media ? $media->url : null;
    }

    /**
     * Accessor: content (alias for body)
     */
    public function getContentAttribute(): ?string
    {
        return $this->body ?? '';
    }

    /**
     * Accessor: category (alias for the first category slug)
     */
    public function getCategoryAttribute(): ?string
    {
        return $this->categories()->first()?->slug;
    }

    /**
     * Accessor: is_published
     */
    public function getIsPublishedAttribute(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Accessor: likes_count
     * Uses eager-loaded counts when available, otherwise queries the relationship.
     */
    public function getLikesCountAttribute(): int
    {
        // If eager-loaded via withCount, use that value
        if (array_key_exists('likes_count', $this->attributes)) {
            return (int) $this->attributes['likes_count'];
        }

        return $this->likes()->where('type', 'like')->count();
    }

    /**
     * Accessor: dislikes_count
     * Uses eager-loaded counts when available, otherwise queries the relationship.
     */
    public function getDislikesCountAttribute(): int
    {
        // If eager-loaded via withCount, use that value
        if (array_key_exists('dislikes_count', $this->attributes)) {
            return (int) $this->attributes['dislikes_count'];
        }

        return $this->likes()->where('type', 'dislike')->count();
    }

    /**
     * Accessor: comments_count
     * Uses eager-loaded counts when available, otherwise queries the relationship.
     */
    public function getCommentsCountAttribute(): int
    {
        // If eager-loaded via withCount, use that value
        if (array_key_exists('comments_count', $this->attributes)) {
            return (int) $this->attributes['comments_count'];
        }

        return $this->comments()->count();
    }

    /**
     * Accessor: gallery_images
     * Returns all media attached to this post with the 'gallery' role.
     */
    public function getGalleryImagesAttribute()
    {
        // Use loaded relationship if available to avoid N+1
        if ($this->relationLoaded('media')) {
            return $this->media->filter(function ($m) {
                return $m->pivot && $m->pivot->role === 'gallery';
            })->values();
        }

        return $this->galleryMedia()->get();
    }

    /**
     * Get featured image from media relationship (primary image)
     * Returns the image media attached to this post with role='featured'
     */
    public function getFeaturedMediaImage()
    {
        // Use loaded relationship if available
        if ($this->relationLoaded('media')) {
            return $this->media->first(function ($m) {
                return $m->pivot && $m->pivot->role === 'featured';
            });
        }

        return $this->media()->wherePivot('role', 'featured')->first();
    }

    /**
     * Get featured image path (CAS/R2 only)
     */
    public function getFeaturedImagePath(): ?string
    {
        $featuredMedia = $this->getFeaturedMediaImage();

        return $featuredMedia ? $featuredMedia->file_path : null;
    }

    /**
     * Set featured image via media attachment
     * Attaches the first image media as featured (by ordering)
     */
    public function setFeaturedImageFromMedia(int $mediaId): bool
    {
        $media = Media::find($mediaId);

        if (! $media || $media->type !== 'image' || $media->user_id !== $this->user_id) {
            return false;
        }

        // Detach existing featured media first (simple approach: detach all images, attach new one)
        $existing = $this->getFeaturedMediaImage();
        if ($existing) {
            $existing?->decrement('usage_count');
        }
        $this->media()->where('type', 'image')->detach();

        // Attach the new featured image
        $this->media()->attach($mediaId);
        Media::find($mediaId)?->increment('usage_count');

        // Update media public status based on post status
        $this->updateAttachedMediaPrivacy();

        return true;
    }

    /**
     * Update privacy status of all attached media based on post status
     */
    public function updateAttachedMediaPrivacy(): void
    {
        $visibility = $this->status === 'published' ? 'public' : 'private';

        $this->media()->update(['visibility' => $visibility]);
    }

    /**
     * Boot method to handle media privacy updates
     */
    protected static function booted(): void
    {
        static::updating(function ($post) {
            // If status changed to/from published, update media privacy
            if ($post->isDirty('status')) {
                $post->updateAttachedMediaPrivacy();
            }

            // Also handle slug change/folder rename
            if ($post->isDirty('slug')) {
                $oldSlug = $post->getOriginal('slug');
                $newSlug = $post->slug;
                if ($oldSlug && $newSlug) {
                    $mediaService = app(\App\Services\Contracts\MediaServiceContract::class);
                    $author = $post->author ?? \App\Models\User::find($post->user_id);
                    if ($author && method_exists($mediaService, 'renameFolder')) {
                        $mediaService->renameFolder(
                            $author,
                            'public',
                            ['posts', $oldSlug],
                            $newSlug
                        );
                    }
                }
            }
        });

        static::deleting(function ($post) {
            // When post is deleted, make all attached media private, decrement usage, and detach
            foreach ($post->media as $media) {
                $media?->decrement('usage_count');
            }
            $post->media()->update(['visibility' => 'private']);
            $post->media()->detach();
        });
    }
}
