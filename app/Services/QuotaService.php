<?php

namespace App\Services;

use App\Models\QuotaCommitment;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * QuotaService
 *
 * Manages lab quota commitments for users on monthly subscriptions.
 *
 * Key Responsibilities:
 * - Monthly quota replenishment on subscription anniversary
 * - Quota status checking and validation
 * - Quota consumption for bookings (deducts from available hours)
 * - Quota commitment for recurring bookings across future months
 * - Threshold warning detection and marking
 *
 * Monthly Quota Model (PRD Section 2.1):
 * - Each user with active subscription gets 1 quota_commitment per month
 * - Quota replenishment triggered on subscription anniversary (daily check)
 * - Unused quota expires at month end (zero carryover)
 * - Quota consumed at booking confirmation (not at start time)
 *
 * Grace Period Logic (CRITICAL):
 * - Subscription enters grace period at anniversary (when ends_at is reached)
 * - During grace period (ends_at < now() but not canceled_at), quota is NOT replenished
 * - Quota only replenished when:
 *   1. User renews their current subscription (new subscription created with same/higher quota)
 *   2. User upgrades to another plan with quota feature
 * - This ensures users cannot use quota without active paid subscription
 *
 * Example Timeline for 50-hour/month plan:
 * - March 1: Subscription starts → quota_commitment created with 50 hours
 * - March 1-15: Books 10 hours → remaining = 40
 * - March 31: ends_at = March 31 (anniversary) → Unused 40 hours expire
 * - April 1: Subscription enters grace period (ends_at < now, canceled_at = NULL)
 * - April 1: NO quota replenishment during grace period
 * - April 5: User renews subscription → New subscription created with April 5 start date
 * - April 5: New quota_commitment created with 50 hours
 * - April 6+: Quota available again for new month (May)
 */
class QuotaService
{
    /**
     * Replenish quota for a user's current month if they have an active subscription.
     *
     * Called by daily scheduler (`quota:replenish-monthly` command)
     * Checks if user's subscription is active (not in grace period) and creates new monthly commitment
     *
     * Grace Period Exclusion (CRITICAL):
     * - Subscriptions in grace period (ends_at < now, canceled_at = NULL) are skipped
     * - Quota only replenished when user has active subscription that hasn't reached anniversary
     * - User must renew/upgrade to get quota during/after grace period
     *
     * Idempotency:
     * - Only creates ONE commitment per user per month
     * - Safe to run multiple times per day (scheduler calls daily at 00:15 UTC)
     * - Returns false if commitment already exists for current month
     *
     * @return bool True if quota was replenished this month, false if already exists or no subscription
     */
    public function replenishMonthlyQuota(User $user): bool
    {
        // Get active lab subscription (excludes grace period AND validates plan has lab quota feature)
        $subscription = $user->activeLabSubscription();
        if (! $subscription) {
            return false; // No active subscription (or in grace period or no lab quota feature)
        }

        // Check if user already has quota for current month
        $thisMonth = now()->startOfMonth();
        if (QuotaCommitment::where('user_id', $user->id)
            ->where('month_date', $thisMonth)
            ->exists()) {
            return false; // Already replenished this month
        }

        // Get quota hours from subscription plan's features (validated by activeLabSubscription)
        $quotaHours = (int) ($subscription->plan->getFeatureValue('lab_hours_monthly') ?? 0);

        // Extra safety check: don't create 0-hour commitments
        if ($quotaHours <= 0) {
            return false;
        }

        // Create new quota commitment for this month
        QuotaCommitment::create([
            'user_id' => $user->id,
            'month_date' => $thisMonth,
            'committed_hours' => (int) $quotaHours,
            'used_hours' => 0,
            'warning_threshold_percent' => 80,
            'warned_at_threshold' => false,
            'replenished_at' => now(),
        ]);

        return true;
    }

    /**
     * Get quota status for a user's specific month (or current if not specified)
     *
     * Returns comprehensive quota information including usage, warning status, and reset date
     *
     * @param  Carbon|null  $month  Month to check (default: current month)
     * @return array Quota status with: total_hours, used_hours, remaining_hours, percentage_used, etc.
     */
    public function getQuotaStatus(User $user, ?Carbon $month = null): array
    {
        $month = ($month ?? now())->startOfMonth();
        $commitment = QuotaCommitment::where('user_id', $user->id)
            ->where('month_date', $month)
            ->first();

        if (! $commitment) {
            return [
                'total_hours' => 0,
                'used_hours' => 0,
                'remaining_hours' => 0,
                'percentage_used' => 0,
                'warning' => null,
                'reset_date' => $month->clone()->addMonth()->startOfMonth(),
                'month' => $month->format('Y-m'),
                'commitment_exists' => false,
            ];
        }

        $remaining = $commitment->committed_hours - $commitment->used_hours;
        $percentageUsed = $commitment->committed_hours > 0
            ? ($commitment->used_hours / $commitment->committed_hours) * 100
            : 0;

        $warning = null;
        if ($percentageUsed >= 80 && ! $commitment->warned_at_threshold) {
            $warning = 'approaching_limit';
        } elseif ($remaining < 0) {
            $warning = 'exceeded_limit';
        }

        return [
            'total_hours' => (int) $commitment->committed_hours,
            'used_hours' => (float) $commitment->used_hours,
            'remaining_hours' => max($remaining, 0),
            'available_for_booking' => max(0, $remaining),
            'percentage_used' => round($percentageUsed, 2),
            'warning_threshold_percent' => $commitment->warning_threshold_percent,
            'warning' => $warning,
            'warned_at_threshold' => $commitment->warned_at_threshold,
            'reset_date' => $month->clone()->addMonth()->startOfMonth(),
            'month' => $month->format('Y-m'),
            'commitment_exists' => true,
            'replenished_at' => $commitment->replenished_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Commit (reserve) quota for a specific month.
     *
     * Used by recurring bookings that span multiple months.
     * Creates quota_commitment if month doesn't exist yet, then deducts hours.
     *
     * @param  Carbon  $month  Month to commit quota for
     * @param  float  $hoursToCommit  Hours to deduct from quota
     * @return bool True if committed successfully, false if insufficient quota
     */
    public function commitQuotaForMonth(User $user, Carbon $month, float $hoursToCommit): bool
    {
        $month = $month->startOfMonth();
        $subscription = $user->activeLabSubscription();

        if (! $subscription) {
            return false; // No active subscription
        }

        // Get or create commitment for this month
        $commitment = QuotaCommitment::where('user_id', $user->id)
            ->where('month_date', $month)
            ->first();

        if (! $commitment) {
            // Create future month commitment if doesn't exist
            $quotaHours = $subscription->plan->getFeatureValue('lab_hours_monthly') ?? 0;

            $commitment = QuotaCommitment::create([
                'user_id' => $user->id,
                'month_date' => $month,
                'committed_hours' => (int) $quotaHours,
                'used_hours' => 0,
                'warning_threshold_percent' => 80,
                'warned_at_threshold' => false,
                'replenished_at' => null, // Mark as not yet auto-replenished
            ]);
        }

        // Check if enough quota available
        $available = $commitment->committed_hours - $commitment->used_hours;
        if ($available < $hoursToCommit) {
            return false; // Not enough quota
        }

        // Deduct quota from this month
        return $commitment->consume((float) $hoursToCommit);
    }

    /**
     * Check if user can book a given number of hours in a specific month
     *
     * @param  float  $hoursNeeded  Hours to book
     * @param  Carbon|null  $month  Month for booking (default: current month)
     * @return bool True if user has sufficient quota
     */
    public function canBook(User $user, float $hoursNeeded, ?Carbon $month = null): bool
    {
        $month = ($month ?? now())->startOfMonth();
        $status = $this->getQuotaStatus($user, $month);

        return ! empty($status['commitment_exists']) && $status['remaining_hours'] >= $hoursNeeded;
    }

    /**
     * Mark the 80% threshold warning as sent for a month
     * Prevents duplicate warning notifications
     *
     * @param  Carbon|null  $month  Month to mark (default: current month)
     */
    public function markThresholdWarning(User $user, ?Carbon $month = null): void
    {
        $month = ($month ?? now())->startOfMonth();
        QuotaCommitment::where('user_id', $user->id)
            ->where('month_date', $month)
            ->update(['warned_at_threshold' => true]);
    }

    /**
     * Get all months with quota that need warning notifications
     *
     * Finds commitments that are above 80% threshold and haven't been warned yet
     *
     * @return \Illuminate\Database\Eloquent\Collection QuotaCommitments needing warnings
     */
    public function getCommitmentsNeedingWarnings()
    {
        return QuotaCommitment::needingWarning()->get();
    }

    /**
     * Get user's quota status for multiple months (e.g., for dashboard display)
     *
     * @param  int  $monthCount  Number of months to retrieve (default: 3)
     * @return array Array of quota status by month
     */
    public function getQuotaStatusForMonths(User $user, int $monthCount = 3): array
    {
        $months = [];
        for ($i = 0; $i < $monthCount; $i++) {
            $month = now()->startOfMonth()->subMonths($monthCount - 1 - $i);
            $months[$month->format('Y-m')] = $this->getQuotaStatus($user, $month);
        }

        return $months;
    }

    /**
     * Get all future month quotas for a user (including current month)
     *
     * Used for recurring bookings to check if quota is available across all needed months
     *
     * @param  Carbon  $endMonth  End month to check through
     * @return array Array of quota commitments
     */
    public function getActiveQuotaCommitments(User $user, Carbon $endMonth): array
    {
        return QuotaCommitment::where('user_id', $user->id)
            ->whereBetween('month_date', [now()->startOfMonth(), $endMonth->endOfMonth()])
            ->get()
            ->mapWithKeys(fn ($q) => [$q->month_date->format('Y-m') => $q->getStatusSummary()])
            ->toArray();
    }

    /**
     * Clean up expired quotas (past month commitments)
     * Called by scheduler periodically for database cleanup
     *
     * @return int Number of deleted records
     */
    public function cleanupExpiredQuotas(): int
    {
        return QuotaCommitment::where('month_date', '<', now()->startOfMonth())
            ->delete();
    }

    /**
     * Get monthly quota statistics for admin reporting
     *
     * @param  Carbon|null  $month  Month to get stats for (default: current month)
     * @return array Global statistics: total_committed, total_used, users_over_quota, etc.
     */
    public function getMonthlyStatistics(?Carbon $month = null): array
    {
        $month = ($month ?? now())->startOfMonth();

        $commitments = QuotaCommitment::where('month_date', $month)->get();

        $totalCommitted = $commitments->sum('committed_hours');
        $totalUsed = $commitments->sum('used_hours');
        $usersOverQuota = $commitments->filter(fn ($c) => $c->used_hours > $c->committed_hours)->count();
        $usersAboveThreshold = $commitments->filter(fn ($c) => $c->isAboveWarningThreshold())->count();

        return [
            'month' => $month->format('Y-m'),
            'total_committed_hours' => (int) $totalCommitted,
            'total_used_hours' => (float) $totalUsed,
            'total_available_hours' => max(0, $totalCommitted - $totalUsed),
            'utilization_percentage' => $totalCommitted > 0 ? round(($totalUsed / $totalCommitted) * 100, 2) : 0,
            'active_users' => $commitments->count(),
            'users_over_quota' => $usersOverQuota,
            'users_above_threshold' => $usersAboveThreshold,
        ];
    }

    /**
     * Restore hours to a user's quota for a specific month.
     * 
     * PRD Section 10: Used when a quota booking is cancelled.
     * Checks that the restore is happening before the anniversary reset date.
     *
     * @param  User  $user  The user to restore hours for
     * @param  Carbon  $month  The month to restore to
     * @param  float  $hoursToRestore  Hours to add back to available quota
     * @return bool True if restored successfully
     */
    public function restoreHours(User $user, Carbon $month, float $hoursToRestore): bool
    {
        $month = $month->startOfMonth();
        
        // PRD Rule: Cannot restore hours if currently after the anniversary reset
        // Anniversary reset for a month 'M' happens on the 1st of month 'M+1'
        $resetDate = $month->clone()->addMonth()->startOfMonth();
        if (now()->greaterThanOrEqualTo($resetDate)) {
            return false;
        }

        $commitment = QuotaCommitment::where('user_id', $user->id)
            ->where('month_date', $month)
            ->first();

        if (!$commitment) {
            return false;
        }

        // Restoring means decreasing 'used_hours'
        $commitment->used_hours = max(0, $commitment->used_hours - $hoursToRestore);
        return $commitment->save();
    }
}
