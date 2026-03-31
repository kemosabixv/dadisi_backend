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
     * Helper to check if user is a supervisor assigned to the space.
     */
    protected function isAssignedSupervisor(User $user, LabBooking $booking): bool
    {
        if (!$user->hasRole('lab_supervisor')) {
            return false;
        }

        return $user->assignedLabSpaces()
            ->where('lab_spaces.id', $booking->lab_space_id)
            ->exists();
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
    public function view(?User $user, LabBooking $labBooking): bool
    {
        if (!$user) return false;
        return $user->id === $labBooking->user_id 
            || $user->can('view_all_lab_bookings')
            || $this->isAssignedSupervisor($user, $labBooking);
    }

    /**
     * Determine whether the user can create bookings.
     * Requires authentication and eligible subscription.
     */
    public function create(?User $user): bool
    {
        if (!$user) return false;
        // Check if user has eligible subscription for lab booking
        $quotaStatus = $this->bookingService->getQuotaStatus($user);
        return $quotaStatus['has_access'] ?? false;
    }

    /**
     * Determine whether guests can create bookings.
     */
    public function guestStore(?User $user): bool
    {
        // Public endpoint, anyone can access (validation/payment handled in service)
        return true;
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
     * Determine whether the user can check in a booking via ID-based endpoint.
     * Note: Researchers MUST use the QR token flow (selfCheckInByToken) to ensure physical presence.
     * This ID-based endpoint is restricted to staff only.
     */
    public function checkIn(User $user, LabBooking $labBooking): bool
    {
        // Staff check-in (Guest or User)
        // Must have permission AND (be admin/manager OR be assigned supervisor for the space)
        if ($user->can('mark_lab_attendance')) {
            if ($user->hasRole(['admin', 'super_admin', 'lab_manager'])) {
                return $labBooking->can_check_in;
            }

            if ($this->isAssignedSupervisor($user, $labBooking)) {
                return $labBooking->can_check_in;
            }
        }

        return false;
    }


    /**
     * Determine whether the user can view all bookings (admin).
     */
    public function viewAll(User $user): bool
    {
        return $user->can('view_all_lab_bookings') || $user->hasRole('lab_supervisor');
    }

    /**
     * Determine whether the user can cancel a booking (staff action).
     * - lab_manager: global access to all labs
     * - admin: global access to all labs
     * - lab_supervisor: only assigned labs
     */
    public function cancelBooking(User $user, LabBooking $labBooking): bool
    {
        if (!$user->can('cancel_lab_booking')) {
            return false;
        }

        // lab_manager: global access
        if ($user->hasRole('lab_manager')) {
            return true;
        }

        // admin: global access
        if ($user->can('manage_lab_spaces')) {
            return true;
        }

        // lab_supervisor: only assigned labs
        if ($this->isAssignedSupervisor($user, $labBooking)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can initiate a refund request.
     * - lab_manager: global access to all labs
     * - admin: global access to all labs
     * - lab_supervisor: only assigned labs (requires approval by Lab Manager/Admin)
     */
    public function initiateRefund(User $user, LabBooking $labBooking): bool
    {
        if (!$user->can('initiate_lab_refund')) {
            return false;
        }

        // lab_manager: global access
        if ($user->hasRole('lab_manager')) {
            return true;
        }

        // admin: global access
        if ($user->can('manage_lab_spaces')) {
            return true;
        }

        // lab_supervisor: only assigned labs
        if ($this->isAssignedSupervisor($user, $labBooking)) {
            return true;
        }

        return false;
    }
}
