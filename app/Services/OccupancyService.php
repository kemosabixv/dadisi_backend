<?php

namespace App\Services;

use App\Models\LabBooking;
use App\Models\LabSpace;
use Carbon\Carbon;

/**
 * OccupancyService
 *
 * Handles real-time occupancy tracking and capacity calculations for lab spaces.
 * Calculates how many booking slots are available vs full for each time slot.
 *
 * Uses `capacity` field for both physical limit and booking slots per hour.
 *
 * Percentage thresholds:
 * - ≤50% = Green (plenty of space)
 * - 51-80% = Yellow (getting full)
 * - 81-99% = Orange (nearly full)
 * - 100% = Red (FULL/no bookings allowed)
 */
class OccupancyService
{
    /**
     * Get occupancy information for a specific time slot.
     *
     * @param LabSpace $space The lab space
     * @param Carbon $slotStart Start time of the slot
     * @param Carbon $slotEnd End time of the slot
     *
     * @return array {
     *     'current': int,           // Number of confirmed/completed bookings
     *     'capacity': int,          // Total slots available per hour
     *     'available': int,         // Available slots remaining
     *     'percentage': int,        // Occupancy % (0-100)
     *     'is_full': bool,          // true if current >= capacity
     *     'is_near_full': bool      // true if percentage >= 80
     * }
     */
    public function getSlotOccupancy(
        LabSpace $space,
        Carbon $slotStart,
        Carbon $slotEnd,
        $prefetchedBookings = null,
        $prefetchedHolds = null
    ): array {
        // Use capacity logic (synchronized to reflect users-per-slot)
        $capacity = max(1, (int) ($space->capacity ?? 1));

        // Count confirmed and completed bookings that overlap this time slot
        // Pending bookings don't count toward occupancy
        if ($prefetchedBookings !== null) {
            $currentCount = $prefetchedBookings->filter(function ($booking) use ($slotStart, $slotEnd) {
                return $booking->starts_at < $slotEnd &&
                       $booking->ends_at > $slotStart &&
                       in_array($booking->status, [
                           LabBooking::STATUS_CONFIRMED,
                           LabBooking::STATUS_COMPLETED,
                       ]);
            })->count();
        } else {
            $currentCount = LabBooking::where('lab_space_id', $space->id)
                ->where('starts_at', '<', $slotEnd)
                ->where('ends_at', '>', $slotStart)
                ->whereIn('status', [
                    LabBooking::STATUS_CONFIRMED,
                    LabBooking::STATUS_COMPLETED,
                ])
                ->count();
        }

        // Count active slot holds (temporary locks during checkout)
        if ($prefetchedHolds !== null) {
            $holdCount = $prefetchedHolds->filter(function ($hold) use ($slotStart, $slotEnd) {
                return $hold->starts_at < $slotEnd &&
                       $hold->ends_at > $slotStart &&
                       $hold->expires_at > now();
            })->count();
        } else {
            $holdCount = \App\Models\SlotHold::where('lab_space_id', $space->id)
                ->where('starts_at', '<', $slotEnd)
                ->where('ends_at', '>', $slotStart)
                ->where('expires_at', '>', now())
                ->count();
        }

        $currentCount += $holdCount;

        // Calculate available slots
        $available = max(0, $capacity - $currentCount);

        // Calculate occupancy percentage (0-100)
        $percentage = (int) floor(($currentCount / $capacity) * 100);

        return [
            'current' => $currentCount,
            'capacity' => $capacity,
            'available' => $available,
            'percentage' => $percentage,
            'is_full' => $currentCount >= $capacity,
            'is_near_full' => $percentage >= 80,
        ];
    }

    /**
     * Check if a time slot has available capacity for booking.
     *
     * @param LabSpace $space
     * @param Carbon $start
     * @param Carbon $end
     *
     * @return bool true if slot has available positions, false if full
     */
    public function canBook(LabSpace $space, Carbon $start, Carbon $end): bool
    {
        $occupancy = $this->getSlotOccupancy($space, $start, $end);

        return !$occupancy['is_full'];
    }

    /**
     * Get occupancy for a date range (multiple days).
     *
     * Returns occupancy data for each hour in each day of the range.
     *
     * @param LabSpace $space
     * @param Carbon $start Start date (inclusive)
     * @param Carbon $end End date (inclusive)
     *
     * @return array {
     *     'YYYY-MM-DD': {
     *         'HH': occupancy data, ...
     *     }
     * }
     */
    public function getDateRangeOccupancy(LabSpace $space, Carbon $start, Carbon $end): array
    {
        $occupancies = [];

        $current = $start->copy()->startOfDay();
        $endDate = $end->copy()->endOfDay();

        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $occupancies[$dateKey] = [];

            // Get operating hours for this space (or default to 8 AM - 8 PM)
            $opensHour = isset($space->opens_at)
                ? (int) Carbon::parse($space->opens_at)->format('H')
                : 8;

            $closesHour = isset($space->closes_at)
                ? (int) Carbon::parse($space->closes_at)->format('H')
                : 20;

            // Generate occupancy for each hour
            for ($hour = $opensHour; $hour < $closesHour; $hour++) {
                $slotStart = $current->copy()->setHour($hour)->setMinute(0)->setSecond(0);
                $slotEnd = $slotStart->copy()->addHour();

                $occupancies[$dateKey][$hour] = $this->getSlotOccupancy($space, $slotStart, $slotEnd);
            }

            $current->addDay();
        }

        return $occupancies;
    }
}
