<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Media extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'user_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'type',
        'is_public',
        'attached_to',
        'attached_to_id',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Owner (user) relationship
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Attached blog post relationship
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'attached_to_id');
    }

    /**
     * Scope: user's own media
     */
    public function scopeOwnedBy($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: public media only
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope: orphaned (unattached) media
     */
    public function scopeOrphaned($query)
    {
        return $query->whereNull('attached_to_id')
            ->orWhere('attached_to', null);
    }

    /**
     * Scope: by type (image, audio, video, pdf)
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if media is accessible to a user
     */
    public function isAccessibleTo($userId): bool
    {
        // Owner can access
        if ($this->user_id === $userId) {
            return true;
        }

        // Public media attached to published post
        if ($this->is_public && $this->attached_to === 'blog_post' && $this->post && $this->post->status === 'published') {
            return true;
        }

        return false;
    }
}
