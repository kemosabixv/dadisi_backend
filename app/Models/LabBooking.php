<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class LabBooking extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = [
        'is_cancellable',
        'is_deadline_reached',
        'duration_hours',
        'is_guest',
        'can_check_in',
        'is_present',
        'payer_name',
        'payer_email',
    ];

    protected $fillable = [
        'lab_space_id',
        'booking_series_id',
        'user_id',
        'guest_name',
        'guest_email',
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
        'booking_reference',
        'payment_method',
        'paid_amount',
        'quota_hours',
        'receipt_number',
        'total_price',
        'booked_at_lab_capacity',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'quota_consumed' => 'boolean',
        'total_price' => 'decimal:2',
        'booked_at_lab_capacity' => 'array',
    ];

    // ==================== Constants ====================

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_PENDING_USER_RESOLUTION = 'pending_user_resolution';

    public const PAYMENT_METHOD_QUOTA = 'quota';
    public const PAYMENT_METHOD_DIRECT = 'direct';
    public const PAYMENT_METHOD_MIXED = 'mixed';

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

    public function bookingSeries(): BelongsTo
    {
        return $this->belongsTo(BookingSeries::class, 'booking_series_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(BookingAuditLog::class, 'booking_id');
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

    /**
     * Get the payment for this booking (polymorphic).
     */
    public function payment(): MorphOne
    {
        return $this->morphOne(\App\Models\Payment::class, 'payable');
    }

    /**
     * Get refund requests for this booking.
     */
    public function refunds()
    {
        return $this->morphMany(\App\Models\Refund::class, 'refundable');
    }

    /**
     * Get attendance logs for this booking.
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'booking_id');
    }

    /**
     * Get the primary attendance record for this booking.
     */
    public function attendanceLog()
    {
        return $this->hasOne(AttendanceLog::class, 'booking_id')->latest();
    }

    /**
     * Generate a unique receipt number.
     */
    public static function generateReceiptNumber(): string
    {
        do {
            $number = 'LB-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        } while (static::where('receipt_number', $number)->exists());

        return $number;
    }

    /**
     * Check if the booking was paid via direct cash/card/mpesa.
     */
    public function isDirectPayment(): bool
    {
        return in_array($this->payment_method, [
            self::PAYMENT_METHOD_DIRECT,
            self::PAYMENT_METHOD_MIXED,
            'card',
            'mpesa',
            'pesapal'
        ]);
    }

    /**
     * Check if the booking was paid via quota.
     */
    public function isQuotaPayment(): bool
    {
        return $this->payment_method === self::PAYMENT_METHOD_QUOTA;
    }

    // ==================== Guest Accessors ====================

    /**
     * Check if this is a guest booking.
     */
    public function getIsGuestAttribute(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Get the payer name (User username or Guest name).
     */
    public function getPayerNameAttribute(): string
    {
        if ($this->relationLoaded('user') && $this->user) {
            return $this->user->username ?? $this->guest_name ?? 'Guest';
        }
        
        return $this->guest_name ?? 'Guest';
    }

    /**
     * Get the payer email.
     */
    public function getPayerEmailAttribute(): ?string
    {
        if ($this->relationLoaded('user') && $this->user) {
            return $this->user->email ?? $this->guest_email;
        }

        return $this->guest_email;
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
        return $query->where('status', self::STATUS_CONFIRMED);
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
        return $query->whereIn('status', [self::STATUS_CONFIRMED, self::STATUS_COMPLETED])
                     ->where('quota_consumed', true);
    }

    /**
     * Scope to upcoming bookings.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>', now())
                     ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
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
        if (!$this->ends_at) {
            return false;
        }

        // According to the 15-Minute Rule: 
        // No-Shows: If you haven't checked in 15 minutes after your slot ends
        return now()->isAfter($this->ends_at->addMinutes(15));
    }

    /**
     * Check if booking can be checked in.
     */
    public function getCanCheckInAttribute(): bool
    {
        return $this->status === self::STATUS_CONFIRMED
            && !$this->checked_in_at
            && now()->isAfter($this->starts_at->subMinutes(15)) // Allow 15 min early check-in
            && !$this->is_past_grace_period;
    }


    /**
     * Check if booking is cancellable.
     */
    public function getIsCancellableAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED])
            && $this->starts_at->isFuture();
    }

    /**
     * Check if the member is present (marked as attended).
     */
    public function getIsPresentAttribute(): bool
    {
        // For users: check specific date-consolidated check-in (PRD 9, Implementation A.5)
        if ($this->checked_in_at) {
            return true;
        }

        // Fallback to attendance logs table
        return $this->attendanceLogs()
            ->where('status', AttendanceLog::STATUS_ATTENDED)
            ->exists();
    }

    /**
     * Get the ID of the staff member who marked attendance.
     */
    public function getCheckedInByAttribute(): ?int
    {
        return $this->attendanceLog?->marked_by_id;
    }

    /**
     * Check if the cancellation deadline has been reached.
     */
    public function getIsDeadlineReachedAttribute(): bool
    {
        $settings = app(\App\Services\SystemSettingService::class);
        $days = (int) $settings->get('lab_booking_cancellation_deadline_days', 1);
        
        return now()->isAfter($this->starts_at->subDays($days));
    }


    /**
     * Get status badge color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_CONFIRMED => 'blue',
            self::STATUS_REJECTED => 'red',
            self::STATUS_CANCELLED => 'gray',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_NO_SHOW => 'orange',
            default => 'gray',
        };
    }
}
