<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Donation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'donor_name',
        'donor_email',
        'donor_phone',
        'county_id',
        'amount',
        'currency',
        'status',
        'reference',
        'receipt_number',
        'payment_id',
        'campaign_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Get the user associated with this donation (optional)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the county for this donation
     */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /**
     * Get the campaign this donation belongs to (optional)
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DonationCampaign::class);
    }

    /**
     * Get the payment associated with this donation
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'payable_id')
            ->where('payable_type', 'donation');
    }

    /**
     * Scope: get pending donations
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: get paid donations
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope: get failed donations
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: get refunded donations
     */
    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    /**
     * Scope: filter by county
     */
    public function scopeByCounty($query, $countyId)
    {
        return $query->where('county_id', $countyId);
    }

    /**
     * Scope: filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Check if donation is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if donation is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Generate unique receipt number
     */
    public static function generateReceiptNumber(): string
    {
        return 'RCP-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
    }

    /**
     * Generate unique donation reference
     */
    public static function generateReference(): string
    {
        return 'DON-' . Str::upper(Str::random(12));
    }

    /**
     * Boot method to auto-generate reference
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->reference)) {
                $model->reference = static::generateReference();
            }
        });
    }
}
