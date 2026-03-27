<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'user_id',
        'media_file_id',
        'folder_id',
        'file_name',
        'mime_type',
        'file_size',
        'usage_count',
        'root_type', // 'personal', 'public'
        'visibility', // 'public', 'private', 'shared'
        'share_token',
        'expires_at',
        'allow_download',
        'temporary_until',
    ];

    protected $appends = [
        'url',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'usage_count' => 'integer',
        'allow_download' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'expires_at' => 'datetime',
        'temporary_until' => 'datetime',
    ];

    /**
     * Owner relationship
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Backwards compatibility for older callsites.
     */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    /**
     * Physical file relationship (CAS)
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'media_file_id');
    }

    /**
     * Folder relationship (Virtual FS)
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    /**
     * Check if media is attached to any entity
     */
    public function isAttached(): bool
    {
        return $this->usage_count > 0 || \DB::table('media_attachments')
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
     * Scope: by visibility
     */
    public function scopeVisibility($query, $visibility)
    {
        return $query->where('visibility', $visibility);
    }

    public function scopePersonal($query)
    {
        return $query->where('root_type', 'personal');
    }

    /**
     * Scope: by type (image, audio, video, pdf)
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function getUrlAttribute(): string
    {
        return $this->file?->getUrl() ?? '';
    }
}
