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
        'visibility', // 'public', 'private', 'shared'
        'share_token',
        'allow_download',
        'temporary_until',
        
        // DEPRECATED
        'is_public',
        'attached_to',
        'attached_to_id',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'allow_download' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'temporary_until' => 'datetime',
        
        // DEPRECATED
        'is_public' => 'boolean',
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
        return $query->where('visibility', 'public');
    }

    /**
     * Scope: by visibility
     */
    public function scopeVisibility($query, $visibility)
    {
        return $query->where('visibility', $visibility);
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
     * Check if media is accessible to a user or public
     */
    public function isAccessibleTo($userId = null): bool
    {
        // 1. Owner can access
        if ($userId && $this->user_id === $userId) {
            return true;
        }

        // 2. Public media is accessible to anyone
        if ($this->visibility === 'public') {
            return true;
        }

        // 3. Shared media is accessible via token (this check is for general access)
        if ($this->visibility === 'shared') {
            return true;
        }

        // DEPRECATED backward compatibility:
        // Public media attached to published post
        if ($this->is_public && $this->attached_to === 'blog_post' && $this->post && $this->post->status === 'published') {
            return true;
        }

        return false;
    }
}
