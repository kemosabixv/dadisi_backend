<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'venue',
        'is_online',
        'online_link',
        'capacity',
        'county_id',
        'image_path',
        'price',
        'currency',
        'status',
        'created_by',
        'published_at',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'capacity' => 'integer',
        'is_online' => 'boolean',
        'price' => 'decimal:2',
        'published_at' => 'datetime',
    ];

    /**
     * Get the county for this event
     */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /**
     * Get the user who created this event
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
        return $this->orders()
            ->where('status', 'paid')
            ->sum('total_amount');
    }

    /**
     * Get total attendees/tickets sold
     */
    public function getTotalAttendees(): int
    {
        return $this->orders()
            ->where('status', 'paid')
            ->sum('quantity');
    }
}

