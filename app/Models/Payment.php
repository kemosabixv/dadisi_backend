<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravelcm\Subscriptions\Models\Subscription;

class Payment extends Model
{
    protected $fillable = [
        'payable_type',
        'payable_id',
        'gateway',
        'method',
        'status',
        'amount',
        'currency',
        'external_reference',
        'order_reference',
        'receipt_url',
        'paid_at',
        'meta',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'meta' => 'array',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the payable model (polymorphic relationship)
     */
    public function payable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the subscription this payment belongs to (if applicable)
     */
    public function subscription(): BelongsTo
    {
        // For subscription payments, get the subscription by id from payable_id
        return $this->belongsTo(Subscription::class, 'payable_id');
    }

    /**
     * Scope for pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for paid payments
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Check if payment is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
