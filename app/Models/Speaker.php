<?php

namespace App\Models;

use App\Models\Media;
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
        // Migration: legacy photo_path removed, now using CAS media_id
        'website_url',
        'linkedin_url',
        'sort_order',
    ];

    protected $casts = [
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
     * Get the full URL for the speaker photo (CAS/R2 only).
     */
    public function getPhotoUrlAttribute(): ?string
    {
        $photoMedia = $this->photoMedia();
        return $photoMedia ? $photoMedia->url : null;
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
        $old = $this->photoMedia();
        if ($old) {
            $old->decrement('usage_count');
        }
        $this->media()->wherePivot('role', 'speaker_photo')->detach();
        $this->media()->attach($mediaId, ['role' => 'speaker_photo']);
        Media::find($mediaId)?->increment('usage_count');
    }

    protected static function boot()
    {
        parent::boot();
        static::updating(function (self $speaker) {
            if ($speaker->isDirty('name')) {
                $oldSlug = \Illuminate\Support\Str::slug($speaker->getOriginal('name'));
                $newSlug = \Illuminate\Support\Str::slug($speaker->name);
                if ($oldSlug && $newSlug && $oldSlug !== $newSlug) {
                    $event = $speaker->event;
                    app(\App\Services\Contracts\MediaServiceContract::class)->renameFolder(
                        $event->creator ?? auth()->user() ?? User::find($event->created_by),
                        'public', 
                        ['speakers', $oldSlug], 
                        $newSlug
                    );
                }
            }
        });

        static::deleting(function (self $speaker) {
            foreach ($speaker->media as $media) {
                $media->decrement('usage_count');
            }
            $speaker->media()->detach();
        });
    }
}

