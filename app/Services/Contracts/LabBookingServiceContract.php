<?php

namespace App\Services\Contracts;

use App\Models\BookingSeries;
use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use App\Models\LabSpace;
use App\Models\User;
use Carbon\Carbon;

interface LabBookingServiceContract
{
    /**
     * Get user's lab quota status.
     */
    public function getQuotaStatus(User $user): array;

    /**
     * Create a new lab booking.
     * Returns array with success status, booking object, and potential race condition metadata.
     */
    public function createBooking(User $user, array $data): array;

    /**
     * Calculate booking price based on user subscription and lab rates.
     */
    public function calculateBookingPrice(User $user, LabSpace $space, float $hours): array;

    /**
     * Cancel a lab booking series.
     */
    public function cancelSeries(BookingSeries $series, string $reason = 'User cancelled'): array;

    /**
     * Cancel a lab booking.
     */
    public function cancelBooking(LabBooking $booking, string $reason = 'User cancelled'): array;

    /**
     * Get refund preview for a series.
     */
    public function refundSeriesPreview(BookingSeries $series): array;

    /**
     * Get refund preview for a booking.
     */
    public function refundPreview(LabBooking $booking): array;

    /**
     * Calculate refund for a booking with specific structure for admin.
     */
    public function calculateRefund(LabBooking $booking): array;

    /**
     * Create a guest lab booking (no user account required).
     */
    public function createGuestBooking(array $data): array;

    /**
     * Check if user can book a space.
     */
    public function canBook(User $user, float $requestedHours, ?LabSpace $lab = null): array;

    /**
     * Check space availability for time range.
     */
    public function checkAvailability(int|LabSpace $space, Carbon $start, Carbon $end, ?int $excludeBookingId = null, ?array $context = null): bool;

    /**
     * Check in to a booking.
     */
    public function checkIn(LabBooking $booking, ?Carbon $checkInAt = null, ?User $staff = null): LabBooking;

    /**
     * Check in via Lab Space static checkin_token.
     */
    public function checkInByToken(User $user, string $token): LabBooking;


    /**
     * Mark booking as no-show.
     */
    public function markNoShow(LabBooking $booking): LabBooking;

    /**
     * Get availability calendar for a space.
     */
    public function getAvailabilityCalendar(LabSpace $space, Carbon $start, Carbon $end): array;

    /**
     * Find an alternative slot for a booking during maintenance/closure.
     */
    public function findAlternativeSlot(
        int $spaceId,
        int $durationHours,
        $blocksToAvoid, // Can be LabMaintenanceBlock or Collection of blocks
        ?int $excludeBookingId = null
    ): ?array;

    /**
     * Release quota for a cancelled booking.
     */
    public function releaseBookingQuota(LabBooking $booking): bool;

    /**
     * Mark attendance for a specific slot.
     */
    public function markSlotAttendance(LabBooking $booking, Carbon $slotStartTime, string $status, ?User $staff = null): bool;

    /**
     * Roll over bookings that conflict with a maintenance block.
     */
    public function rollOverBookings(LabMaintenanceBlock $block): array;

    /**
     * Resolve a booking conflict manually by selecting a new slot.
     */
    public function resolveConflict(LabBooking $booking, array $data): array;

    /**
     * Initiate a booking by creating slot holds.
     */
    public function initiateBooking(?User $user, array $data): array;

    /**
     * Confirm a booking (Stage 2).
     */
    public function confirmBooking(string $reference, ?string $paymentId, string $paymentMethod): array;

    /**
     * Confirm a guest booking.
     */
    public function confirmGuest(string $reference, string $paymentId, string $paymentMethod, array $guestData): array;

    /**
     * Renew a slot hold.
     */
    public function renewHold(string $reference): array;

    /**
     * Get hold by reference.
     */
    public function getHoldByReference(string $reference): ?\App\Models\SlotHold;

    /**
     * Pre-fetch availability data for a range.
     */
    public function preFetchAvailabilityData(int $spaceId, Carbon $start, Carbon $end): array;

    /**
     * Discover recurring slots.
     */
    public function discoverRecurringSlots(int $spaceId, array $data, ?User $user): array;

    /**
     * Discover flexible slots.
     */
    public function discoverFlexibleSlots(int $spaceId, array $data, ?User $user): array;
}
