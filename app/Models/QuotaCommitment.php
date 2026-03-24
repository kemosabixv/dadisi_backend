<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * QuotaCommitment Model
 *
 * Represents a user's monthly lab quota commitment.
 *
 * Each user with an active lab subscription gets one quota_commitment record per month,
 * created on their subscription anniversary date.
 *
 * Quota is:
 * - Created from the user's plan's lab_hours_monthly feature
 * - Consumed when lab bookings are confirmed
 * - Expires at the end of the month (zero carryover to next month)
 *
 * @property int $id
 * @property int $user_id
 * @property string $month_date Date string (YYYY-MM-01) for month of quota
 * @property int $committed_hours Total monthly quota hours
 * @property float $used_hours Hours consumed by confirmed bookings
 * @property int $warning_threshold_percent Percentage to trigger warning (default 80)
 * @property bool $warned_at_threshold Whether user was notified at threshold
 * @property Carbon|null $replenished_at When quota was auto-replenished
 * @property int|null $series_id For recurring bookings quota commitments
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $user
 * @property BookingSeries|null $series
 */
class QuotaCommitment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'month_date',
        'committed_hours',
        'used_hours',
        'warning_threshold_percent',
        'warned_at_threshold',
        'replenished_at',
        'series_id',
    ];

    protected $casts = [
        'month_date' => 'date',
        'committed_hours' => 'integer',
        'used_hours' => 'decimal:2',
        'warning_threshold_percent' => 'integer',
        'warned_at_threshold' => 'boolean',
        'replenished_at' => 'datetime',
    ];

    /**
     * Relationship: User who owns this quota commitment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: BookingSeries (for recurring bookings) that this commitment supports
     * Can be null for non-recurring bookings that just deduct from quota
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(BookingSeries::class, 'series_id');
    }

    /**
     * Get remaining quota hours for this month
     */
    public function getRemainingHours(): float
    {
        return max(0, $this->committed_hours - $this->used_hours);
    }

    /**
     * Get percentage of quota used
     */
    public function getPercentageUsed(): float
    {
        if ($this->committed_hours == 0) {
            return 0;
        }

        return ($this->used_hours / $this->committed_hours) * 100;
    }

    /**
     * Check if quota is above warning threshold
     */
    public function isAboveWarningThreshold(): bool
    {
        return $this->getPercentageUsed() >= $this->warning_threshold_percent;
    }

    /**
     * Check if quota is exhausted (no remaining hours)
     */
    public function isExhausted(): bool
    {
        return $this->getRemainingHours() <= 0;
    }

    /**
     * Mark that user has been warned at threshold
     */
    public function markThresholdWarned(): void
    {
        $this->update(['warned_at_threshold' => true]);
    }

    /**
     * Mark that quota was replenished
     */
    public function markReplenished(): void
    {
        $this->update(['replenished_at' => now()]);
    }

    /**
     * Consume quota hours (deduct from available quota)
     *
     * @param  float  $hours  Hours to consume
     * @return bool True if successfully consumed, false if insufficient quota
     */
    public function consume(float $hours): bool
    {
        if ($this->getRemainingHours() < $hours) {
            return false;
        }

        $this->update([
            'used_hours' => $this->used_hours + $hours,
        ]);

        return true;
    }

    /**
     * Get quota status summary
     */
    public function getStatusSummary(): array
    {
        return [
            'total_hours' => $this->committed_hours,
            'used_hours' => (float) $this->used_hours,
            'remaining_hours' => $this->getRemainingHours(),
            'percentage_used' => round($this->getPercentageUsed(), 2),
            'warning_threshold_percent' => $this->warning_threshold_percent,
            'is_exhausted' => $this->isExhausted(),
            'is_above_threshold' => $this->isAboveWarningThreshold(),
            'warned_at_threshold' => $this->warned_at_threshold,
            'month' => $this->month_date->format('Y-m'),
            'reset_date' => $this->month_date->clone()->addMonth()->startOfMonth(),
        ];
    }

    /**
     * Scope: Get current month's quota for a user
     */
    public function scopeCurrentMonth($query, User $user)
    {
        return $query->where('user_id', $user->id)
            ->where('month_date', now()->startOfMonth());
    }

    /**
     * Scope: Get specific month's quota for a user
     */
    public function scopeForMonth($query, User $user, Carbon $month)
    {
        return $query->where('user_id', $user->id)
            ->where('month_date', $month->startOfMonth());
    }

    /**
     * Scope: Get future months (after today)
     */
    public function scopeFutureMonths($query, User $user)
    {
        return $query->where('user_id', $user->id)
            ->where('month_date', '>', now()->startOfMonth());
    }

    /**
     * Scope: Get months that need warning sent
     */
    public function scopeNeedingWarning($query)
    {
        return $query->where('warned_at_threshold', false)
            ->where('warning_threshold_percent', '>', 0)
            ->whereRaw('(used_hours / committed_hours * 100) >= warning_threshold_percent');
    }

    /**
     * Scope: Get past months (before current month)
     */
    public function scopePastMonths($query, User $user)
    {
        return $query->where('user_id', $user->id)
            ->where('month_date', '<', now()->startOfMonth());
    }
}
