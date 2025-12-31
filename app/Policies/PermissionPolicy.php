<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Illuminate\Auth\Access\Response;

class PermissionPolicy
{
    /**
     * Allow super_admin to do anything.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return null; // Continue with other checks
    }

    /**
     * Determine whether the user can view any permissions.
     * Only super admins can manage permissions
     */
    public function viewAny(User $user): bool
    {
        return $user->can('manage_permissions');
    }

    /**
     * Determine whether the user can view a specific permission.
     */
    public function view(User $user, Permission $permission): bool
    {
        return $user->can('manage_permissions');
    }

    /**
     * Determine whether the user can create permissions.
     */
    public function create(User $user): bool
    {
        return $user->can('manage_permissions');
    }

    /**
     * Determine whether the user can update a permission.
     */
    public function update(User $user, Permission $permission): bool
    {
        return $user->can('manage_permissions');
    }

    /**
     * Determine whether the user can delete a permission.
     */
    public function delete(User $user, Permission $permission): bool
    {
        return $user->can('manage_permissions');
    }

    /**
     * Determine whether the user can restore permissions.
     */
    public function restore(User $user, Permission $permission): bool
    {
        return $user->can('manage_permissions');
    }

    /**
     * Determine whether the user can permanently delete permissions.
     */
    public function forceDelete(User $user, Permission $permission): bool
    {
        return $user->can('manage_permissions');
    }
}

