<?php

namespace App\Policies;

use App\Models\LabSpace;
use App\Models\User;

class LabSpacePolicy
{
    /**
     * Helper to check if user is a supervisor assigned to the space.
     */
    protected function isAssignedSupervisor(User $user, LabSpace $labSpace): bool
    {
        if (!$user->hasRole('lab_supervisor')) {
            return false;
        }

        return $user->assignedLabSpaces()
            ->where('lab_spaces.id', $labSpace->id)
            ->exists();
    }

    /**
     * Determine whether the user can view any lab spaces.
     * Public access - anyone can browse lab spaces.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific lab space.
     * - lab_manager: global access
     * - lab_supervisor: only assigned labs
     * - admin: global access
     * - others: public view allowed
     */
    public function view(?User $user, LabSpace $labSpace): bool
    {
        // Public access - anyone can view lab space details
        if (!$user) {
            return true;
        }

        // lab_manager: global access
        if ($user->hasRole('lab_manager')) {
            return true;
        }

        // lab_supervisor: only assigned labs
        if ($user->hasRole('lab_supervisor')) {
            return $user->assignedLabSpaces()
                ->where('lab_spaces.id', $labSpace->id)
                ->exists();
        }

        // admin: global access
        if ($user->can('manage_lab_spaces')) {
            return true;
        }

        // Default: public view allowed
        return true;
    }

    /**
     * Determine whether the user can create lab spaces.
     * Only admins and lab_managers can create labs.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['lab_manager', 'admin']) || $user->can('manage_lab_spaces');
    }

    /**
     * Determine whether the user can update a lab space.
     * - lab_manager: global access to all fields
     * - lab_supervisor: can edit all fields of assigned labs only
     * - admin: global access
     */
    public function update(User $user, LabSpace $labSpace): bool
    {
        if (!$user->can('edit_lab_space')) {
            return false;
        }

        // lab_manager: global access to all fields
        if ($user->hasRole('lab_manager')) {
            return true;
        }

        // lab_supervisor: can edit all fields of assigned labs only
        if ($user->hasRole('lab_supervisor')) {
            return $user->assignedLabSpaces()
                ->where('lab_spaces.id', $labSpace->id)
                ->exists();
        }

        // admin: global access
        if ($user->can('manage_lab_spaces')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete a lab space.
     * Only admins and lab_managers can delete labs.
     */
    public function delete(User $user, LabSpace $labSpace): bool
    {
        return $user->hasRole(['lab_manager', 'admin']) || $user->can('manage_lab_spaces');
    }

    /**
     * Determine whether the user can restore a deleted lab space.
     * Requires manage_lab_spaces permission.
     */
    public function restore(User $user, LabSpace $labSpace): bool
    {
        return $user->can('manage_lab_spaces');
    }

    /**
     * Determine whether the user can permanently delete a lab space.
     * Requires manage_lab_spaces permission.
     */
    public function forceDelete(User $user, LabSpace $labSpace): bool
    {
        return $user->can('manage_lab_spaces');
    }

    /**
     * Determine whether the user can disable bookings for a lab space.
     * Only lab_manager, admin, and superadmin can disable bookings.
     * Lab supervisors CANNOT disable/enable bookings.
     */
    public function disableBookings(User $user, LabSpace $labSpace): bool
    {
        if (!$user->can('disable_lab_bookings')) {
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

        return false;
    }

    /**
     * Determine whether the user can enable bookings for a lab space.
     * Only lab_manager, admin, and superadmin can enable bookings.
     * Lab supervisors CANNOT disable/enable bookings.
     */
    public function enableBookings(User $user, LabSpace $labSpace): bool
    {
        if (!$user->can('enable_lab_bookings')) {
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

        return false;
    }
}
