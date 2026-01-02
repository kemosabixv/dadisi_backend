<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'currency',
        'quantity',
        'available',
        'order_limit',
        'is_active',
        'available_until',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'available' => 'integer',
        'order_limit' => 'integer',
        'is_active' => 'boolean',
        'available_until' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /**
     * Check if this ticket tier is currently available for purchase.
     * A ticket is available if:
     * - is_active is true
     * - Not sold out (available > 0 or quantity is null for unlimited)
     * - available_until has not passed (or is null)
     */
    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->isSoldOut()) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        return true;
    }

    /**
     * Check if this ticket tier has passed its sale deadline.
     */
    public function isExpired(): bool
    {
        if ($this->available_until === null) {
            return false;
        }

        return $this->available_until->isPast();
    }

    /**
     * Check if this ticket tier is sold out.
     */
    public function isSoldOut(): bool
    {
        // Unlimited quantity
        if ($this->quantity === null) {
            return false;
        }

        return $this->available <= 0;
    }

    /**
     * Scope to filter only available tickets.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('quantity')
                  ->orWhere('available', '>', 0);
            })
            ->where(function ($q) {
                $q->whereNull('available_until')
                  ->orWhere('available_until', '>', now());
            });
    }
}
