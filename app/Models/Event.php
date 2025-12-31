<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'is_online',
        'online_link',
        'capacity',
        'waitlist_enabled',
        'waitlist_capacity',
        'county_id',
        'image_path',
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
        'waitlist_capacity' => 'integer',
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
    public function hasCapacity(): bool
    {
        if (!$this->capacity) return true;
        return $this->getTotalAttendees() < $this->capacity;
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
     * Get the full URL for the image. (Matches frontend image_url)
     */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        return asset('storage/' . $this->image_path);
    }
}
