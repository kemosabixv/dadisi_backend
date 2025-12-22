<?php

namespace App\Policies;

use App\Models\LabSpace;
use App\Models\User;

class LabSpacePolicy
{
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
     * Public access - anyone can view lab space details.
     */
    public function view(?User $user, LabSpace $labSpace): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create lab spaces.
     * Requires manage_lab_spaces permission.
     */
    public function create(User $user): bool
    {
        return $user->can('manage_lab_spaces');
    }

    /**
     * Determine whether the user can update a lab space.
     * Requires manage_lab_spaces permission.
     */
    public function update(User $user, LabSpace $labSpace): bool
    {
        return $user->can('manage_lab_spaces');
    }

    /**
     * Determine whether the user can delete a lab space.
     * Requires manage_lab_spaces permission.
     */
    public function delete(User $user, LabSpace $labSpace): bool
    {
        return $user->can('manage_lab_spaces');
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
}
