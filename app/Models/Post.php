<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'hero_image_path',
        'meta_title',
        'meta_description',
        'is_featured',
        'views_count',
        'author_id',
        'content',
        'category',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'views_count' => 'integer', // Cast views_count as integer for proper handling
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'featured_image',
        'content',
        'is_published',
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
        if ($key === 'title' && !empty($value) && empty($this->getAttribute('slug'))) {
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
     * Media relationship (attached images)
     */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'post_media', 'post_id', 'media_id')
            ->withTimestamps();
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
     * Accessor: featured_image (backward compatibility with hero_image_path field)
     */
    public function getFeaturedImageAttribute(): ?string
    {
        $path = $this->getFeaturedImagePath();
        return $path ? asset('storage/' . $path) : null;
    }

    /**
     * Accessor: content (alias for body)
     */
    public function getContentAttribute(): string
    {
        return $this->body;
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
     * Mutator: featured_image
     */
    public function setFeaturedImageAttribute(?string $value): void
    {
        $this->hero_image_path = $value;
    }

    /**
     * Get featured image from media relationship (primary image)
     * Returns the first image media attached to this post
     */
    public function getFeaturedMediaImage()
    {
        return $this->media()->where('type', 'image')->first();
    }

    /**
     * Get featured image path (prioritizes media system, falls back to direct field)
     * This provides a unified interface for getting the featured image
     */
    public function getFeaturedImagePath(): ?string
    {
        // First try to get from media system
        $featuredMedia = $this->getFeaturedMediaImage();
        if ($featuredMedia) {
            return $featuredMedia->file_path;
        }

        // Fall back to direct field
        return $this->hero_image_path;
    }

    /**
     * Set featured image via media attachment
     * Attaches the first image media as featured (by ordering)
     */
    public function setFeaturedImageFromMedia(int $mediaId): bool
    {
        $media = Media::find($mediaId);

        if (!$media || $media->type !== 'image' || $media->user_id !== $this->user_id) {
            return false;
        }

        // Detach existing featured media first (simple approach: detach all images, attach new one)
        $this->media()->where('type', 'image')->detach();

        // Attach the new featured image
        $this->media()->attach($mediaId);

        // Update media public status based on post status
        $this->updateAttachedMediaPrivacy();

        return true;
    }

    /**
     * Update privacy status of all attached media based on post status
     */
    public function updateAttachedMediaPrivacy(): void
    {
        $isPublic = $this->status === 'published';

        $this->media()->update(['is_public' => $isPublic]);
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
        });

        static::deleting(function ($post) {
            // When post is deleted, make all media private again
            $post->media()->update([
                'is_public' => false,
                'attached_to' => null,
                'attached_to_id' => null,
            ]);
        });
    }
}
