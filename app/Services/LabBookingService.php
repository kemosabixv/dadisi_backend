<?php

namespace App\Services;

use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\LabMaintenanceBlock;
use App\Models\Plan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LabBookingService
{
    /**
     * Get the user's current plan.
     * Checks both direct plan relationship and active subscription.
     */
    protected function getUserPlan(User $user): ?Plan
    {
        // First check direct plan relationship
        if ($user->plan) {
            return $user->plan;
        }

        // Fall back to active subscription's plan
        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->whereNull('canceled_at')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->with('plan')
            ->first();

        return $subscription?->plan;
    }

    /**
     * Get the lab hours limit for a user's plan.
     * 
     * @return float|null null for unlimited, 0 for no access, >0 for limit
     */
    protected function getLabHoursLimit(User $user): ?float
    {
        $plan = $this->getUserPlan($user);
        
        if (!$plan) {
            return 0; // No plan means no access
        }

        // Use SystemFeature-based approach
        $value = $plan->getFeatureValue('lab_hours_monthly', 0);
        
        // -1 represents unlimited
        if ($value === -1 || $value === '-1') {
            return null; // null = unlimited
        }
        
        return (float) $value;
    }

    /**
     * Check if user can book lab space based on subscription quota.
     *
     * @param User $user
     * @param float $requestedHours
     * @return array
     */
    public function canBook(User $user, float $requestedHours): array
    {
        $plan = $this->getUserPlan($user);

        if (!$plan) {
            return [
                'allowed' => false,
                'reason' => 'no_subscription',
                'message' => 'You need an active subscription to book lab space.',
            ];
        }

        $monthlyLimit = $this->getLabHoursLimit($user);

        // Check if plan explicitly denies lab access (0 hours)
        if ($monthlyLimit === 0.0) {
            return [
                'allowed' => false,
                'reason' => 'plan_not_eligible',
                'message' => 'Lab space booking is not available on your current plan. Please upgrade.',
            ];
        }

        // Unlimited access
        if ($monthlyLimit === null) {
            return [
                'allowed' => true,
                'remaining_hours' => null,
                'unlimited' => true,
            ];
        }

        // Calculate used hours this month
        $usedHours = $this->getUsedHoursThisMonth($user);
        $remainingHours = max(0, $monthlyLimit - $usedHours);

        if ($requestedHours > $remainingHours) {
            return [
                'allowed' => false,
                'reason' => 'quota_exceeded',
                'message' => "You have {$remainingHours}h remaining this month. Requested: {$requestedHours}h.",
                'remaining_hours' => $remainingHours,
                'limit' => $monthlyLimit,
            ];
        }

        return [
            'allowed' => true,
            'remaining_hours' => $remainingHours - $requestedHours,
            'limit' => $monthlyLimit,
        ];
    }

    /**
     * Get user's quota status for display.
     *
     * @param User $user
     * @return array
     */
    public function getQuotaStatus(User $user): array
    {
        $plan = $this->getUserPlan($user);

        if (!$plan) {
            return ['has_access' => false, 'reason' => 'no_subscription'];
        }

        $limit = $this->getLabHoursLimit($user);

        // 0 hours = no access
        if ($limit === 0.0) {
            return ['has_access' => false, 'reason' => 'plan_not_eligible'];
        }

        $usedHours = $this->getUsedHoursThisMonth($user);
        $isUnlimited = $limit === null;

        return [
            'has_access' => true,
            'plan_name' => $plan->name,
            'limit' => $isUnlimited ? null : $limit,
            'unlimited' => $isUnlimited,
            'used' => (float) $usedHours,
            'remaining' => $isUnlimited ? null : max(0, $limit - $usedHours),
            'resets_at' => now()->endOfMonth()->toISOString(),
        ];
    }

    /**
     * Get used hours for the current month.
     *
     * @param User $user
     * @return float
     */
    protected function getUsedHoursThisMonth(User $user): float
    {
        return (float) $user->labBookings()
            ->whereIn('status', [LabBooking::STATUS_APPROVED, LabBooking::STATUS_COMPLETED])
            ->where('quota_consumed', true)
            ->whereMonth('starts_at', now()->month)
            ->whereYear('starts_at', now()->year)
            ->get()
            ->sum(function ($booking) {
                return $booking->duration_hours;
            });
    }

    /**
     * Check if a time slot is available for a lab space.
     *
     * @param int $spaceId
     * @param Carbon $start
     * @param Carbon $end
     * @param int|null $excludeBookingId Exclude this booking when checking (for updates)
     * @return bool
     */
    public function checkAvailability(int $spaceId, Carbon $start, Carbon $end, ?int $excludeBookingId = null): bool
    {
        // Check for overlapping bookings
        $overlappingBookings = LabBooking::where('lab_space_id', $spaceId)
            ->overlapping($start, $end)
            ->when($excludeBookingId, function ($query, $id) {
                return $query->where('id', '!=', $id);
            })
            ->exists();

        if ($overlappingBookings) {
            return false;
        }

        // Check for maintenance blocks
        $maintenanceBlocks = LabMaintenanceBlock::where('lab_space_id', $spaceId)
            ->overlapping($start, $end)
            ->exists();

        return !$maintenanceBlocks;
    }

    /**
     * Determine if user's bookings should be auto-approved.
     * Uses SystemFeature for lab_auto_approve.
     *
     * @param User $user
     * @return bool
     */
    public function shouldAutoApprove(User $user): bool
    {
        $plan = $this->getUserPlan($user);

        if (!$plan) {
            return false;
        }

        // Check the lab_auto_approve system feature
        return (bool) $plan->getFeatureValue('lab_auto_approve', false);
    }

    /**
     * Create a new booking.
     *
     * @param User $user
     * @param array $data
     * @return LabBooking
     * @throws \Exception
     */
    public function createBooking(User $user, array $data): LabBooking
    {
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);
        $durationHours = $startsAt->diffInMinutes($endsAt) / 60;

        // Check quota
        $quotaCheck = $this->canBook($user, $durationHours);
        if (!$quotaCheck['allowed']) {
            throw new \Exception($quotaCheck['message']);
        }

        // Check availability
        if (!$this->checkAvailability($data['lab_space_id'], $startsAt, $endsAt)) {
            throw new \Exception('This time slot is not available. Please select a different time.');
        }

        // Determine initial status
        $status = $this->shouldAutoApprove($user) 
            ? LabBooking::STATUS_APPROVED 
            : LabBooking::STATUS_PENDING;

        return DB::transaction(function () use ($user, $data, $status) {
            $booking = LabBooking::create([
                'lab_space_id' => $data['lab_space_id'],
                'user_id' => $user->id,
                'title' => $data['title'] ?? null,
                'purpose' => $data['purpose'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'slot_type' => $data['slot_type'] ?? LabBooking::SLOT_HOURLY,
                'recurrence_rule' => $data['recurrence_rule'] ?? null,
                'status' => $status,
                'quota_consumed' => true, // Consume quota immediately on booking
            ]);

            return $booking->load('labSpace', 'user');
        });
    }

    /**
     * Approve a booking.
     *
     * @param LabBooking $booking
     * @param string|null $notes
     * @return LabBooking
     */
    public function approveBooking(LabBooking $booking, ?string $notes = null): LabBooking
    {
        $booking->update([
            'status' => LabBooking::STATUS_APPROVED,
            'admin_notes' => $notes,
        ]);

        return $booking->fresh();
    }

    /**
     * Reject a booking.
     *
     * @param LabBooking $booking
     * @param string $reason
     * @return LabBooking
     */
    public function rejectBooking(LabBooking $booking, string $reason): LabBooking
    {
        $booking->update([
            'status' => LabBooking::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'quota_consumed' => false, // Refund quota
        ]);

        return $booking->fresh();
    }

    /**
     * Cancel a booking.
     *
     * @param LabBooking $booking
     * @return LabBooking
     */
    public function cancelBooking(LabBooking $booking): LabBooking
    {
        $booking->update([
            'status' => LabBooking::STATUS_CANCELLED,
            'quota_consumed' => false, // Refund quota
        ]);

        return $booking->fresh();
    }

    /**
     * Check in a booking.
     *
     * @param LabBooking $booking
     * @return LabBooking
     */
    public function checkIn(LabBooking $booking): LabBooking
    {
        $booking->update([
            'checked_in_at' => now(),
        ]);

        return $booking->fresh();
    }

    /**
     * Check out a booking.
     *
     * @param LabBooking $booking
     * @return LabBooking
     */
    public function checkOut(LabBooking $booking): LabBooking
    {
        $checkedInAt = $booking->checked_in_at;
        $actualDuration = $checkedInAt ? now()->diffInMinutes($checkedInAt) / 60 : null;

        $booking->update([
            'checked_out_at' => now(),
            'actual_duration_hours' => $actualDuration ? round($actualDuration, 2) : null,
            'status' => LabBooking::STATUS_COMPLETED,
        ]);

        return $booking->fresh();
    }

    /**
     * Mark a booking as no-show (manual action by admin/lab_manager).
     *
     * @param LabBooking $booking
     * @return LabBooking
     */
    public function markNoShow(LabBooking $booking): LabBooking
    {
        $booking->update([
            'status' => LabBooking::STATUS_NO_SHOW,
            // Note: quota is still consumed for no-shows as a penalty
        ]);

        return $booking->fresh();
    }

    /**
     * Get availability calendar data for a lab space.
     *
     * @param LabSpace $space
     * @param Carbon $start
     * @param Carbon $end
     * @return array
     */
    public function getAvailabilityCalendar(LabSpace $space, Carbon $start, Carbon $end): array
    {
        // Get all bookings in range
        $bookings = LabBooking::where('lab_space_id', $space->id)
            ->where('starts_at', '>=', $start)
            ->where('ends_at', '<=', $end)
            ->whereNotIn('status', [LabBooking::STATUS_CANCELLED, LabBooking::STATUS_REJECTED])
            ->with('user:id,username')
            ->get();

        // Get all maintenance blocks in range
        $maintenanceBlocks = LabMaintenanceBlock::where('lab_space_id', $space->id)
            ->where('starts_at', '>=', $start)
            ->where('ends_at', '<=', $end)
            ->get();

        $events = [];

        foreach ($bookings as $booking) {
            $events[] = [
                'id' => 'booking_' . $booking->id,
                'title' => $booking->title ?? 'Booked',
                'start' => $booking->starts_at->toISOString(),
                'end' => $booking->ends_at->toISOString(),
                'type' => 'booking',
                'status' => $booking->status,
                'user' => $booking->user ? $booking->user->username : null,
            ];
        }

        foreach ($maintenanceBlocks as $block) {
            $events[] = [
                'id' => 'maintenance_' . $block->id,
                'title' => $block->title,
                'start' => $block->starts_at->toISOString(),
                'end' => $block->ends_at->toISOString(),
                'type' => 'maintenance',
                'reason' => $block->reason,
            ];
        }

        return $events;
    }
}
