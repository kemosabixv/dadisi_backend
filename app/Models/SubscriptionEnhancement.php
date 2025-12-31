<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubscriptionEnhancement extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'subscription_id',
        'status',
        'payment_method',
        'metadata',
        'pesapal_account_reference',
        'pesapal_subscription_frequency',
        'last_pesapal_recurring_at',
    ];

    protected $casts = [
        'last_renewal_attempt_at' => 'datetime',
        'grace_period_started_at' => 'datetime',
        'grace_period_starts_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'grace_period_expires_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'next_auto_renewal_at' => 'datetime',
        'last_pesapal_recurring_at' => 'datetime',
        'pesapal_recurring_enabled' => 'boolean',
        'metadata' => 'json',
    ];

    /**
     * Get the subscription
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PlanSubscription::class, 'subscription_id');
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Normalize status values for legacy expectations in tests.
     */
    public function getStatusAttribute($value)
    {
        if ($value === 'payment_failed') {
            return 'failed';
        }

        if ($value === 'payment_pending') {
            return 'pending';
        }

        return $value;
    }

    /**
     * Normalize incoming status values to DB enum values.
     */
    public function setStatusAttribute($value)
    {
        if ($value === 'failed') {
            $value = 'payment_failed';
        }

        if ($value === 'pending') {
            $value = 'payment_pending';
        }

        $this->attributes['status'] = $value;
    }

}
