<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EventOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'event_id',
        'quantity',
        'unit_price',
        'total_amount',
        'currency',
        'status',
        'reference',
        'receipt_number',
        'payment_id',
        'purchased_at',
        // Guest checkout fields
        'guest_name',
        'guest_email',
        'guest_phone',
        // Discount tracking
        'promo_code_id',
        'promo_discount_amount',
        'subscriber_discount_amount',
        'original_amount',
        // Check-in
        'qr_code_token',
        'checked_in_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'promo_discount_amount' => 'decimal:2',
        'subscriber_discount_amount' => 'decimal:2',
        'quantity' => 'integer',
        'purchased_at' => 'datetime',
        'checked_in_at' => 'datetime',
    ];

    /**
     * Get the user who made this order (null for guest checkout)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the event for this order
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the promo code used for this order
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    /**
     * Get the payment associated with this order
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'payable_id')
            ->where('payable_type', 'event_order');
    }

    /**
     * Scope: get pending orders
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: get paid orders
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope: get failed orders
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: get refunded orders
     */
    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    /**
     * Scope: filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Check if order is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if order is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if this is a guest order
     */
    public function isGuestOrder(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Check if attendee has checked in
     */
    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }

    /**
     * Get the attendee name (user or guest)
     */
    public function getAttendeeNameAttribute(): string
    {
        if ($this->user) {
            return $this->user->name ?? $this->user->username ?? 'User';
        }
        return $this->guest_name ?? 'Guest';
    }

    /**
     * Get the attendee email (user or guest)
     */
    public function getAttendeeEmailAttribute(): ?string
    {
        if ($this->user) {
            return $this->user->email;
        }
        return $this->guest_email;
    }

    /**
     * Get total discount applied
     */
    public function getTotalDiscountAttribute(): float
    {
        return (float) $this->promo_discount_amount + (float) $this->subscriber_discount_amount;
    }

    /**
     * Generate unique receipt number
     */
    public static function generateReceiptNumber(): string
    {
        return 'TKT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
    }

    /**
     * Generate unique order reference
     */
    public static function generateReference(): string
    {
        return 'ORD-' . Str::upper(Str::random(12));
    }

    /**
     * Generate unique QR code token
     */
    public static function generateQrToken(): string
    {
        return 'TKT-' . Str::upper(Str::random(16));
    }

    /**
     * Boot method to auto-generate reference and QR token
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->reference)) {
                $model->reference = static::generateReference();
            }
            if (empty($model->qr_code_token)) {
                $model->qr_code_token = static::generateQrToken();
            }
        });
    }
}


