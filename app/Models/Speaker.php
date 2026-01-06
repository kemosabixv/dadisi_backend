<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Speaker extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'name',
        'email',
        'company',
        'designation',
        'bio',
        'photo_path',
        'website_url',
        'linkedin_url',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'photo_url',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the full URL for the speaker photo.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        // First try to get from media system
        $photoMedia = $this->photoMedia();
        if ($photoMedia) {
            return asset('storage/' . $photoMedia->file_path);
        }

        // Fall back to direct field
        if (!$this->photo_path) {
            return null;
        }

        return asset('storage/' . $this->photo_path);
    }

    /**
     * Media relationship (attached images via polymorphic pivot)
     */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'attachable', 'media_attachments')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the speaker photo media (role = 'speaker_photo')
     */
    public function photoMedia(): ?Media
    {
        return $this->media()->wherePivot('role', 'speaker_photo')->first();
    }

    /**
     * Attach media as speaker photo (replaces existing)
     */
    public function setPhotoMedia(int $mediaId): void
    {
        $this->media()->wherePivot('role', 'speaker_photo')->detach();
        $this->media()->attach($mediaId, ['role' => 'speaker_photo']);
    }
}

