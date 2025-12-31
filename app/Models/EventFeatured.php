<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EventFeatured Model
 *
 * Represents featured status and metadata for events.
 * Allows admins to feature events with priority levels.
 */
class EventFeatured extends Model
{
    protected $table = 'event_featured';

    protected $fillable = [
        'event_id',
        'priority',
        'featured_by',
        'reason',
        'is_active',
        'featured_at',
    ];

    protected $casts = [
        'featured_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the event this feature belongs to
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the user who featured this event
     */
    public function featuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'featured_by');
    }
}
