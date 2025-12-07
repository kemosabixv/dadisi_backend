<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionEnhancement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'subscription_id',
        'status',
        'payment_failure_state',
        'renewal_attempts',
        'max_renewal_attempts',
        'last_renewal_attempt_at',
        'grace_period_started_at',
        'grace_period_ends_at',
        'next_retry_at',
        'payment_method',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'last_renewal_attempt_at' => 'datetime',
        'grace_period_started_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'next_retry_at' => 'datetime',
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
     * Check if in grace period
     */
    public function isInGracePeriod(): bool
    {
        return $this->status === 'grace_period' &&
               $this->grace_period_ends_at &&
               $this->grace_period_ends_at->isFuture();
    }

    /**
     * Check if grace period has ended
     */
    public function hasGracePeriodEnded(): bool
    {
        return $this->status === 'grace_period' &&
               $this->grace_period_ends_at &&
               $this->grace_period_ends_at->isPast();
    }

    /**
     * Check if payment is retryable
     */
    public function isRetryable(): bool
    {
        return $this->renewal_attempts < $this->max_renewal_attempts &&
               in_array($this->payment_failure_state, ['retry_immediate', 'retry_delayed']);
    }

    /**
     * Increment renewal attempts
     */
    public function incrementRetryAttempts(): void
    {
        $this->increment('renewal_attempts');
        $this->update(['last_renewal_attempt_at' => now()]);
    }

    /**
     * Set retry schedule
     */
    public function scheduleRetry(\DateTime $retryAt, string $failureReason): void
    {
        $this->update([
            'next_retry_at' => $retryAt,
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
            'grace_period_started_at' => now(),
            'grace_period_ends_at' => now()->addDays($days),
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
