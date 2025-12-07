<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'county_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'status',
        'published_at',
        'featured_image',
        'meta_title',
        'meta_description',
        'is_featured',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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
}
