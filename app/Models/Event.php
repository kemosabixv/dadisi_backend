<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'category_id',
        'organizer_id',
        'venue',
        'google_maps_url',
        'is_online',
        'online_link',
        'capacity',
        'waitlist_enabled',
        'county_id',
        'price',
        'currency',
        'status',
        'event_type',
        'featured',
        'featured_until',
        'created_by',
        'published_at',
        'registration_deadline',
        'starts_at',
        'ends_at',
    ];

    protected $appends = [
        'image_url',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'registration_deadline' => 'datetime',
        'capacity' => 'integer',
        'waitlist_enabled' => 'boolean',
        'is_online' => 'boolean',
        'price' => 'decimal:2',
        'published_at' => 'datetime',
        'featured' => 'boolean',
        'featured_until' => 'datetime',
    ];

    /**
     * Get the county for this event
     */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /**
     * Get the category for this event
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    /**
     * Get the user who created this event (staff/admin usually)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the organizer of this event
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * Get all tickets for this event
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Get all promo codes for this event
     */
    public function promoCodes(): HasMany
    {
        return $this->hasMany(PromoCode::class);
    }

    /**
     * Get all registrations for this event
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /**
     * Get all speakers for this event
     */
    public function speakers(): HasMany
    {
        return $this->hasMany(Speaker::class);
    }

    /**
     * Get all tags for this event
     */
    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(EventTag::class, 'event_tag', 'event_id', 'tag_id');
    }

    /**
     * Get all event orders for this event
     */
    public function orders(): HasMany
    {
        return $this->hasMany(EventOrder::class);
    }


    /**
     * Scope: get active events
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope: get upcoming events
     */
    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>', now());
    }

    /**
     * Scope: get paid events
     */
    public function scopePaid($query)
    {
        return $query->whereNotNull('price')
            ->where('price', '>', 0);
    }

    /**
     * Scope: get free events
     */
    public function scopeFree($query)
    {
        return $query->where('price', null)
            ->orWhere('price', 0);
    }

    /**
     * Scope: get featured events
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true)
            ->where(function ($q) {
                $q->whereNull('featured_until')
                  ->orWhere('featured_until', '>', now());
            });
    }

    /**
     * Scope: filter by county
     */
    public function scopeByCounty($query, $countyId)
    {
        return $query->where('county_id', $countyId);
    }

    /**
     * Get total revenue from ticket sales
     */
    public function getTotalRevenue(): float
    {
        return (float) $this->orders()
            ->where('status', 'paid')
            ->sum('total_amount');
    }

    /**
     * Get total attendees (confirmed/attended registrations)
     */
    public function getTotalAttendees(): int
    {
        return $this->registrations()
            ->whereIn('status', ['confirmed', 'attended'])
            ->count();
    }

    /**
     * Check if event has available capacity
     */
    public function hasCapacity(int $requested = 1): bool
    {
        return $this->getRemainingCapacity() - $this->getPendingSpotsCount() >= $requested;
    }

    /**
     * Get remaining capacity
     */
    public function getRemainingCapacity(): ?int
    {
        if (!$this->capacity) return null;
        return max(0, $this->capacity - $this->getTotalAttendees());
    }

    /**
     * Get count of pending spots from active payment attempts
     */
    public function getPendingSpotsCount(): int
    {
        return (int) $this->orders()
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->sum('quantity');
    }

    /**
     * Check if registration is open
     */
    public function isRegistrationOpen(): bool
    {
        if ($this->status !== 'published') return false;
        if (now()->isAfter($this->starts_at)) return false;
        if ($this->registration_deadline && now()->isAfter($this->registration_deadline)) return false;
        return true;
    }

    /**
     * Get the full URL for the image (CAS/R2 only).
     */
    public function getImageUrlAttribute(): ?string
    {
        $featuredMedia = $this->featuredMedia();
        return $featuredMedia ? $featuredMedia->url : null;
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
     * Get the featured image media (role = 'featured')
     */
    public function featuredMedia(): ?Media
    {
        return $this->media()->wherePivot('role', 'featured')->first();
    }

    /**
     * Get gallery images (role = 'gallery')
     */
    public function galleryMedia()
    {
        return $this->media()->wherePivot('role', 'gallery');
    }

    /**
     * Attach media as featured image (replaces existing featured)
     */
    public function setFeaturedMedia(int $mediaId): void
    {
        $old = $this->featuredMedia();
        if ($old) {
            $old->decrement('usage_count');
        }

        $this->media()->wherePivot('role', 'featured')->detach();
        $this->media()->attach($mediaId, ['role' => 'featured']);
        Media::find($mediaId)?->increment('usage_count');
    }

    /**
     * Attach media as gallery images
     */
    public function addGalleryMedia(array $mediaIds): void
    {
        foreach ($mediaIds as $id) {
            if (!$this->media()->wherePivot('role', 'gallery')->where('media_id', $id)->exists()) {
                $this->media()->attach($id, ['role' => 'gallery']);
                Media::find($id)?->increment('usage_count');
            }
        }
    }

    protected static function boot()
    {
        parent::boot();
        static::updating(function (self $event) {
            if ($event->isDirty('slug')) {
                $oldSlug = $event->getOriginal('slug');
                $newSlug = $event->slug;
                if ($oldSlug && $newSlug) {
                    app(\App\Services\Contracts\MediaServiceContract::class)->renameFolder(
                        $event->creator ?? auth()->user() ?? User::find($event->created_by),
                        'public', 
                        ['events', $oldSlug], 
                        $newSlug
                    );
                }
            }
        });

        static::deleting(function (self $event) {
            foreach ($event->media as $media) {
                $media->decrement('usage_count');
            }
            $event->media()->detach();
        });
    }

    /**
     * Check if the event is a paid event.
     */
    public function getIsPaidAttribute(): bool
    {
        return $this->price > 0;
    }

    /**
     * Check if the event is free.
     */
    public function getIsFreeAttribute(): bool
    {
        return !$this->is_paid;
    }
}
