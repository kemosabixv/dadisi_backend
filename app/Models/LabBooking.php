<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class LabBooking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lab_space_id',
        'user_id',
        'title',
        'purpose',
        'starts_at',
        'ends_at',
        'slot_type',
        'recurrence_rule',
        'recurrence_parent_id',
        'status',
        'admin_notes',
        'rejection_reason',
        'checked_in_at',
        'checked_out_at',
        'actual_duration_hours',
        'quota_consumed',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'quota_consumed' => 'boolean',
        'actual_duration_hours' => 'decimal:2',
    ];

    // ==================== Constants ====================

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NO_SHOW = 'no_show';

    public const SLOT_HOURLY = 'hourly';
    public const SLOT_HALF_DAY = 'half_day';
    public const SLOT_FULL_DAY = 'full_day';

    // ==================== Relationships ====================

    /**
     * Get the lab space for this booking.
     */
    public function labSpace(): BelongsTo
    {
        return $this->belongsTo(LabSpace::class);
    }

    /**
     * Get the user who made this booking.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent booking for recurring bookings.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(LabBooking::class, 'recurrence_parent_id');
    }

    /**
     * Get child bookings for recurring series.
     */
    public function children(): HasMany
    {
        return $this->hasMany(LabBooking::class, 'recurrence_parent_id');
    }

    // ==================== Scopes ====================

    /**
     * Scope to pending bookings.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to approved bookings.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope to completed bookings.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to bookings for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to bookings in the current month.
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('starts_at', now()->month)
                     ->whereYear('starts_at', now()->year);
    }

    /**
     * Scope to bookings that count towards quota.
     */
    public function scopeQuotaConsuming($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_COMPLETED])
                     ->where('quota_consumed', true);
    }

    /**
     * Scope to upcoming bookings.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>', now())
                     ->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /**
     * Scope to bookings that overlap with a given time range.
     */
    public function scopeOverlapping($query, Carbon $start, Carbon $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->where(function ($q2) use ($start, $end) {
                $q2->where('starts_at', '<', $end)
                   ->where('ends_at', '>', $start);
            });
        })->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_REJECTED]);
    }

    // ==================== Accessors ====================

    /**
     * Get the duration in hours.
     */
    public function getDurationHoursAttribute(): float
    {
        if (!$this->starts_at || !$this->ends_at) {
            return 0;
        }

        return round($this->starts_at->diffInMinutes($this->ends_at) / 60, 2);
    }

    /**
     * Check if the booking is past the grace period for check-in.
     * Grace period is 30 minutes after start time.
     */
    public function getIsPastGracePeriodAttribute(): bool
    {
        if (!$this->starts_at) {
            return false;
        }

        return now()->isAfter($this->starts_at->addMinutes(30));
    }

    /**
     * Check if booking can be checked in.
     */
    public function getCanCheckInAttribute(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && !$this->checked_in_at
            && !$this->is_past_grace_period;
    }

    /**
     * Check if booking can be checked out.
     */
    public function getCanCheckOutAttribute(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->checked_in_at
            && !$this->checked_out_at;
    }

    /**
     * Check if booking is cancellable.
     */
    public function getIsCancellableAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED])
            && $this->starts_at->isFuture();
    }

    /**
     * Get status badge color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_APPROVED => 'blue',
            self::STATUS_REJECTED => 'red',
            self::STATUS_CANCELLED => 'gray',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_NO_SHOW => 'orange',
            default => 'gray',
        };
    }
}
