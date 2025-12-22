<?php

namespace App\Policies;

use App\Models\LabBooking;
use App\Models\User;
use App\Services\LabBookingService;

class LabBookingPolicy
{
    protected LabBookingService $bookingService;

    public function __construct(LabBookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * Determine whether the user can view any bookings.
     * Users can see their own bookings, admins can see all.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view their own bookings
        return true;
    }

    /**
     * Determine whether the user can view a specific booking.
     * Users can view their own bookings or with view_all_lab_bookings permission.
     */
    public function view(User $user, LabBooking $labBooking): bool
    {
        return $user->id === $labBooking->user_id 
            || $user->can('view_all_lab_bookings');
    }

    /**
     * Determine whether the user can create bookings.
     * Requires authentication and eligible subscription.
     */
    public function create(User $user): bool
    {
        // Check if user has eligible subscription for lab booking
        $quotaStatus = $this->bookingService->getQuotaStatus($user);
        return $quotaStatus['has_access'] ?? false;
    }

    /**
     * Determine whether the user can update a booking.
     * Only own pending bookings can be updated.
     */
    public function update(User $user, LabBooking $labBooking): bool
    {
        // Admins can update any booking
        if ($user->can('view_all_lab_bookings')) {
            return true;
        }

        // Users can only update their own pending bookings
        return $user->id === $labBooking->user_id 
            && $labBooking->status === LabBooking::STATUS_PENDING;
    }

    /**
     * Determine whether the user can delete (cancel) a booking.
     * Users can cancel their own pending or approved future bookings.
     */
    public function delete(User $user, LabBooking $labBooking): bool
    {
        // Admins can cancel any booking
        if ($user->can('view_all_lab_bookings')) {
            return true;
        }

        // Users can cancel their own cancellable bookings
        return $user->id === $labBooking->user_id 
            && $labBooking->is_cancellable;
    }

    /**
     * Determine whether the user can approve a booking.
     * Requires approve_lab_bookings permission.
     */
    public function approve(User $user, LabBooking $labBooking): bool
    {
        return $user->can('approve_lab_bookings') 
            && $labBooking->status === LabBooking::STATUS_PENDING;
    }

    /**
     * Determine whether the user can reject a booking.
     * Requires approve_lab_bookings permission.
     */
    public function reject(User $user, LabBooking $labBooking): bool
    {
        return $user->can('approve_lab_bookings') 
            && $labBooking->status === LabBooking::STATUS_PENDING;
    }

    /**
     * Determine whether the user can check in a booking.
     * Requires mark_lab_attendance permission.
     */
    public function checkIn(User $user, LabBooking $labBooking): bool
    {
        return $user->can('mark_lab_attendance') 
            && $labBooking->can_check_in;
    }

    /**
     * Determine whether the user can check out a booking.
     * Requires mark_lab_attendance permission.
     */
    public function checkOut(User $user, LabBooking $labBooking): bool
    {
        return $user->can('mark_lab_attendance') 
            && $labBooking->can_check_out;
    }

    /**
     * Determine whether the user can mark a booking as no-show.
     * Requires mark_lab_attendance permission.
     */
    public function markNoShow(User $user, LabBooking $labBooking): bool
    {
        return $user->can('mark_lab_attendance') 
            && $labBooking->status === LabBooking::STATUS_APPROVED
            && $labBooking->is_past_grace_period
            && !$labBooking->checked_in_at;
    }

    /**
     * Determine whether the user can view all bookings (admin).
     */
    public function viewAll(User $user): bool
    {
        return $user->can('view_all_lab_bookings');
    }
}
