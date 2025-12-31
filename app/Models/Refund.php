<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'refundable_type',
        'refundable_id',
        'payment_id',
        'processed_by',
        'amount',
        'currency',
        'original_amount',
        'status',
        'reason',
        'customer_notes',
        'admin_notes',
        'gateway',
        'gateway_refund_id',
        'gateway_response',
        'requested_at',
        'approved_at',
        'processed_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'gateway_response' => 'array',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Refund status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Refund reason constants
     */
    public const REASON_CANCELLATION = 'cancellation';
    public const REASON_DUPLICATE = 'duplicate';
    public const REASON_CUSTOMER_REQUEST = 'customer_request';
    public const REASON_FRAUD = 'fraud';
    public const REASON_OTHER = 'other';

    /**
     * Get the refundable entity (EventOrder, Donation, etc.)
     */
    public function refundable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the payment being refunded
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the user who processed the refund
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope: pending refunds
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: approved refunds
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope: completed refunds
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Check if refund is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if refund is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if refund can be processed
     */
    public function canBeProcessed(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /**
     * Calculate refund percentage
     */
    public function getRefundPercentageAttribute(): float
    {
        if ($this->original_amount <= 0) {
            return 0;
        }
        
        return round(($this->amount / $this->original_amount) * 100, 2);
    }

    /**
     * Get reason display text
     */
    public function getReasonDisplayAttribute(): string
    {
        $reasons = [
            self::REASON_CANCELLATION => 'Event Cancellation',
            self::REASON_DUPLICATE => 'Duplicate Payment',
            self::REASON_CUSTOMER_REQUEST => 'Customer Request',
            self::REASON_FRAUD => 'Fraudulent Transaction',
            self::REASON_OTHER => 'Other',
        ];

        return $reasons[$this->reason] ?? ucfirst($this->reason);
    }

    /**
     * Approve the refund
     */
    public function approve(\Illuminate\Contracts\Auth\Authenticatable $approver): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_at' => now(),
            'processed_by' => $approver->getAuthIdentifier(),
        ]);
    }

    /**
     * Reject the refund
     */
    public function reject(\Illuminate\Contracts\Auth\Authenticatable $rejector, ?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'processed_by' => $rejector->getAuthIdentifier(),
            'admin_notes' => $reason ?? $this->admin_notes,
        ]);
    }

    /**
     * Mark as processing
     */
    public function markProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark as completed
     */
    public function markCompleted(?string $gatewayRefundId = null, ?array $gatewayResponse = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'gateway_refund_id' => $gatewayRefundId ?? $this->gateway_refund_id,
            'gateway_response' => $gatewayResponse ?? $this->gateway_response,
        ]);
    }

    /**
     * Mark as failed
     */
    public function markFailed(?array $gatewayResponse = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'gateway_response' => $gatewayResponse ?? $this->gateway_response,
        ]);
    }
}
