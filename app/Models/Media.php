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

    protected $appends = [
        'url',
        'original_url',
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
     * Alias for owner() relationship for backward compatibility
     */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    /**
     * Posts that use this media (polymorphic many-to-many via pivot table)
     */
    public function posts(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphedByMany(Post::class, 'attachable', 'media_attachments')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Events that use this media (polymorphic many-to-many via pivot table)
     */
    public function events(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphedByMany(Event::class, 'attachable', 'media_attachments')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Donation campaigns that use this media (polymorphic many-to-many via pivot table)
     */
    public function campaigns(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphedByMany(DonationCampaign::class, 'attachable', 'media_attachments')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Speakers that use this media (polymorphic many-to-many via pivot table)
     */
    public function speakers(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphedByMany(Speaker::class, 'attachable', 'media_attachments')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Check if media is attached to any entity
     */
    public function isAttached(): bool
    {
        return \DB::table('media_attachments')
            ->where('media_id', $this->id)
            ->exists();
    }

    /**
     * Get all attachments (for showing where media is used)
     */
    public function getAttachments(): array
    {
        return \DB::table('media_attachments')
            ->where('media_id', $this->id)
            ->get()
            ->map(function ($attachment) {
                return [
                    'type' => class_basename($attachment->attachable_type),
                    'id' => $attachment->attachable_id,
                    'role' => $attachment->role,
                ];
            })
            ->toArray();
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

    /**
     * Get the public URL for the media file.
     */
    public function getUrlAttribute(): string
    {
        return url('/storage' . $this->file_path);
    }

    /**
     * Get the original URL (same as url for now).
     */
    public function getOriginalUrlAttribute(): string
    {
        return $this->url;
    }
}
