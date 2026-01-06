<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Like extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'likeable_id',
        'likeable_type',
        'type',
    ];

    /**
     * Get the user who created the like/dislike.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent likeable model (Post, Comment, etc.).
     */
    public function likeable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope: filter by like type
     */
    public function scopeLikes($query)
    {
        return $query->where('type', 'like');
    }

    /**
     * Scope: filter by dislike type
     */
    public function scopeDislikes($query)
    {
        return $query->where('type', 'dislike');
    }
}
