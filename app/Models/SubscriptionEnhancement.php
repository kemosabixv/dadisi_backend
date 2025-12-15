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
        'payment_failure_state',
        'renewal_attempts',
        'renewal_attempt_count',
        'max_renewal_attempts',
        'last_renewal_attempt_at',
        'last_renewal_result',
        'last_renewal_error',
        'next_auto_renewal_at',
        'grace_period_started_at',
        'grace_period_starts_at',
        'grace_period_ends_at',
        'grace_period_expires_at',
        'next_retry_at',
        'payment_method',
        'failure_reason',
        'metadata',
        'grace_period_status',
        'grace_period_reason',
        'renewal_mode',
        'renewal_notes',
    ];

    protected $casts = [
        'last_renewal_attempt_at' => 'datetime',
        'grace_period_started_at' => 'datetime',
        'grace_period_starts_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'grace_period_expires_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'next_auto_renewal_at' => 'datetime',
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

    /**
     * Check if in grace period
     */
    public function isInGracePeriod(): bool
    {
         $endsAt = $this->grace_period_expires_at ?? $this->grace_period_ends_at ?? null;
         return ($this->grace_period_status === 'active' || $this->status === 'grace_period') &&
             $endsAt && $endsAt->isFuture();
    }

    /**
     * Check if grace period has ended
     */
    public function hasGracePeriodEnded(): bool
    {
         $endsAt = $this->grace_period_expires_at ?? $this->grace_period_ends_at ?? null;
         return ($this->grace_period_status === 'expired' || $this->status === 'grace_period') &&
             $endsAt && $endsAt->isPast();
    }

    /**
     * Check if payment is retryable
     */
    public function isRetryable(): bool
    {
        $attempts = $this->renewal_attempt_count ?? $this->renewal_attempts ?? 0;
        $max = $this->max_renewal_attempts ?? 3;
        return $attempts < $max && in_array($this->payment_failure_state, ['retry_immediate', 'retry_delayed']);
    }

    /**
     * Increment renewal attempts
     */
    public function incrementRetryAttempts(): void
    {
        if (isset($this->renewal_attempt_count)) {
            $this->increment('renewal_attempt_count');
        } else {
            $this->increment('renewal_attempts');
        }
        $this->update(['last_renewal_attempt_at' => now()]);
    }

    /**
     * Set retry schedule
     */
    public function scheduleRetry(\DateTime $retryAt, string $failureReason): void
    {
        $this->update([
            'next_retry_at' => $retryAt,
            'next_auto_renewal_at' => $retryAt,
            'failure_reason' => $failureReason,
        ]);
    }

    /**
     * Mark as payment pending
     */
    public function markPaymentPending(): void
    {
        $this->update([
            'status' => 'payment_pending',
            'payment_failure_state' => null,
        ]);
    }

    /**
     * Mark as payment failed
     */
    public function markPaymentFailed(string $failureState, string $reason): void
    {
        $this->update([
            'status' => 'payment_failed',
            'payment_failure_state' => $failureState,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Enter grace period
     */
    public function enterGracePeriod(int $days = 14): void
    {
        $this->update([
            'status' => 'grace_period',
            'grace_period_status' => 'active',
            'grace_period_started_at' => now(),
            'grace_period_starts_at' => now(),
            'grace_period_ends_at' => now()->addDays($days),
            'grace_period_expires_at' => now()->addDays($days),
        ]);
    }

    /**
     * Suspend subscription
     */
    public function suspend(): void
    {
        $this->update(['status' => 'suspended']);
    }

    /**
     * Cancel subscription
     */
    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }
}
