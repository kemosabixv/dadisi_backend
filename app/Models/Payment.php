<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravelcm\Subscriptions\Models\Subscription;

class Payment extends Model
{
    use HasFactory;
    protected $fillable = [
        'payable_type',
        'payable_id',
        'payer_id',
        'gateway',
        'method',
        'payment_method',
        'status',
        'amount',
        'currency',
        'description',
        'reference',
        'county',
        'external_reference',
        'order_reference',
        'transaction_id',
        'pesapal_order_id',
        'receipt_url',
        'paid_at',
        'refunded_at',
        'refunded_by',
        'refund_reason',
        'meta',
        'metadata',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'meta' => 'array',
        'metadata' => 'array',
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
     * Get the payer (user who made the payment)
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
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
