<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumThread extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'user_id',
        'county_id',
        'title',
        'slug',
        'is_pinned',
        'is_locked',
        'last_post_id',
        'views_count',
        'posts_count',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
        'views_count' => 'integer',
        'posts_count' => 'integer',
    ];

    /**
     * Get the county this thread is tagged with.
     */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /**
     * Get the category this thread belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'category_id');
    }

    /**
     * Get the author of this thread.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all posts (replies) in this thread.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'thread_id');
    }

    /**
     * Get the last post in this thread.
     */
    public function lastPost(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'last_post_id');
    }

    /**
     * Scope: Pinned threads first.
     */
    public function scopePinnedFirst($query)
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('created_at');
    }

    /**
     * Scope: Active (not locked) threads.
     */
    public function scopeActive($query)
    {
        return $query->where('is_locked', false);
    }

    /**
     * Increment view count.
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    /**
     * Update post count and last post reference.
     */
    public function refreshPostStats(): void
    {
        $this->posts_count = $this->posts()->count();
        $this->last_post_id = $this->posts()->latest()->value('id');
        $this->save();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get tags associated with this thread.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ForumTag::class, 'forum_thread_tag', 'thread_id', 'tag_id')
            ->withTimestamps();
    }

    /**
     * Sync tags and update usage counts.
     */
    public function syncTags(array $tagIds): void
    {
        $oldTags = $this->tags->pluck('id')->toArray();
        $this->tags()->sync($tagIds);
        
        // Decrement old tags that were removed
        $removed = array_diff($oldTags, $tagIds);
        ForumTag::whereIn('id', $removed)->decrement('usage_count');
        
        // Increment new tags that were added
        $added = array_diff($tagIds, $oldTags);
        ForumTag::whereIn('id', $added)->increment('usage_count');
    }
}
