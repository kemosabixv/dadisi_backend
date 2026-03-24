<?php

namespace App\Policies;

use App\Models\LabMaintenanceBlock;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LabMaintenanceBlockPolicy
{
    use HandlesAuthorization;

    /**
     * Helper to check if user is a supervisor assigned to the lab space.
     */
    protected function isAssignedSupervisor(User $user, LabMaintenanceBlock $block): bool
    {
        if (!$user->hasRole('lab_supervisor')) {
            return false;
        }

        return $user->assignedLabSpaces()
            ->where('lab_spaces.id', $block->lab_space_id)
            ->exists();
    }

    /**
     * Determine whether the user can view any maintenance blocks.
     * - lab_manager: global access
     * - lab_supervisor: only assigned labs
     * - admin: global access
     */
    public function viewAny(User $user): bool
    {
        return $user->can('manage_lab_maintenance') || $user->hasRole(['lab_manager', 'lab_supervisor']);
    }

    /**
     * Determine whether the user can view a specific maintenance block.
     * - lab_manager: global access
     * - lab_supervisor: only assigned labs
     * - admin: global access
     */
    public function view(User $user, LabMaintenanceBlock $block): bool
    {
        // lab_manager: global access
        if ($user->hasRole('lab_manager')) {
            return true;
        }

        // lab_supervisor: only assigned labs
        if ($user->hasRole('lab_supervisor')) {
            return $this->isAssignedSupervisor($user, $block);
        }

        // admin: global access
        if ($user->can('manage_lab_maintenance')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create a maintenance block.
     * Requires manage_lab_maintenance permission.
     */
    public function create(User $user): bool
    {
        return $user->can('manage_lab_maintenance');
    }

    /**
     * Determine whether the user can update a maintenance block.
     * - lab_manager: global access
     * - lab_supervisor: only assigned labs
     * - admin: global access
     */
    public function update(User $user, LabMaintenanceBlock $block): bool
    {
        if (!$user->can('manage_lab_maintenance')) {
            return false;
        }

        // lab_manager: global access
        if ($user->hasRole('lab_manager')) {
            return true;
        }

        // lab_supervisor: only assigned labs
        if ($user->hasRole('lab_supervisor')) {
            return $this->isAssignedSupervisor($user, $block);
        }

        // admin: global access
        if ($user->can('manage_lab_maintenance')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete a maintenance block.
     * Same as update.
     */
    public function delete(User $user, LabMaintenanceBlock $block): bool
    {
        return $this->update($user, $block);
    }

    /**
     * Determine whether the user can restore a maintenance block.
     */
    public function restore(User $user, LabMaintenanceBlock $block): bool
    {
        return $this->update($user, $block);
    }

    /**
     * Determine whether the user can permanently delete a maintenance block.
     */
    public function forceDelete(User $user, LabMaintenanceBlock $block): bool
    {
        return $this->delete($user, $block);
    }
}
