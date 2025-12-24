<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pending Payment Model
 * 
 * Tracks payment sessions from initiation to completion.
 * Replaces cache-only approach for more reliable payment tracking.
 */
class PendingPayment extends Model
{
    protected $fillable = [
        'payment_id',
        'transaction_id',
        'user_id',
        'subscription_id',
        'plan_id',
        'amount',
        'currency',
        'status',
        'gateway',
        'billing_period',
        'metadata',
        'expires_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Create a pending payment from payment data.
     */
    public static function createFromPaymentData(array $paymentData, string $paymentId): self
    {
        return self::create([
            'payment_id' => $paymentId,
            'transaction_id' => $paymentData['transaction_id'] ?? null,
            'user_id' => $paymentData['user_id'],
            'subscription_id' => $paymentData['order_id'] ?? null,
            'plan_id' => $paymentData['plan_id'] ?? null,
            'amount' => $paymentData['amount'] ?? 0,
            'currency' => $paymentData['currency'] ?? 'KES',
            'status' => 'pending',
            'gateway' => $paymentData['gateway'] ?? 'mock',
            'billing_period' => $paymentData['billing_period'] ?? 'month',
            'metadata' => $paymentData,
            'expires_at' => now()->addHours(1),
        ]);
    }

    /**
     * Find a pending payment by payment ID.
     */
    public static function findByPaymentId(string $paymentId): ?self
    {
        return self::where('payment_id', $paymentId)->first();
    }

    /**
     * Check if payment is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired' || 
               ($this->expires_at && $this->expires_at->isPast());
    }

    /**
     * Check if payment can be completed.
     */
    public function canBeCompleted(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    /**
     * Mark payment as completed.
     */
    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        \App\Models\AuditLog::log('payment.completed', $this, null, ['status' => 'completed'], 'Payment completed');
    }

    /**
     * Mark payment as failed.
     */
    public function markFailed(?string $reason = null): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['failure_reason'] = $reason;
        
        $this->update([
            'status' => 'failed',
            'metadata' => $metadata,
        ]);
        \App\Models\AuditLog::log('payment.failed', $this, null, ['status' => 'failed', 'reason' => $reason], 'Payment failed');
    }

    /**
     * Mark payment as expired.
     */
    public function markExpired(): void
    {
        $this->update(['status' => 'expired']);
        \App\Models\AuditLog::log('payment.expired', $this, null, ['status' => 'expired'], 'Payment expired by system');
    }

    /**
     * Mark payment as cancelled.
     */
    public function markCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
        \App\Models\AuditLog::log('payment.cancelled', $this, null, ['status' => 'cancelled'], 'Payment cancelled');
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PlanSubscription::class, 'subscription_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '<', now());
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
