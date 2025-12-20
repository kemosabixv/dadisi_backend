<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'created_by',
        'requested_deletion_at',
        'deletion_requested_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'requested_deletion_at' => 'datetime',
    ];

    protected $appends = ['post_count'];

    /**
     * Posts relationship
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_category');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deletionRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deletion_requested_by');
    }

    /**
     * Get post count for this category
     */
    public function getPostCountAttribute(): int
    {
        return $this->posts()->published()->count();
    }

    /**
     * Scope for categories pending deletion
     */
    public function scopePendingDeletion(Builder $query): Builder
    {
        return $query->whereNotNull('requested_deletion_at');
    }

    /**
     * Scope for categories owned by a user
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Request deletion of this category
     */
    public function requestDeletion(int $userId): void
    {
        $this->update([
            'requested_deletion_at' => now(),
            'deletion_requested_by' => $userId,
        ]);
    }

    /**
     * Clear deletion request
     */
    public function clearDeletionRequest(): void
    {
        $this->update([
            'requested_deletion_at' => null,
            'deletion_requested_by' => null,
        ]);
    }
}

