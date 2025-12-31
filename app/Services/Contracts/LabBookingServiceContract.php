<?php

namespace App\Services\Contracts;

use App\Models\LabBooking;
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
     */
    public function createBooking(User $user, array $data): LabBooking;

    /**
     * Cancel a lab booking.
     */
    public function cancelBooking(LabBooking $booking): LabBooking;

    /**
     * Check if user can book a space.
     */
    public function canBook(User $user, float $requestedHours): array;

    /**
     * Check space availability for time range.
     */
    public function checkAvailability(int $spaceId, Carbon $start, Carbon $end, ?int $excludeBookingId = null): bool;

    /**
     * Approve a booking.
     */
    public function approveBooking(LabBooking $booking, ?string $notes = null): LabBooking;

    /**
     * Reject a booking.
     */
    public function rejectBooking(LabBooking $booking, string $reason): LabBooking;

    /**
     * Check in to a booking.
     */
    public function checkIn(LabBooking $booking): LabBooking;

    /**
     * Check out from a booking.
     */
    public function checkOut(LabBooking $booking): LabBooking;

    /**
     * Mark booking as no-show.
     */
    public function markNoShow(LabBooking $booking): LabBooking;

    /**
     * Get availability calendar for a space.
     */
    public function getAvailabilityCalendar(LabSpace $space, Carbon $start, Carbon $end): array;
}
