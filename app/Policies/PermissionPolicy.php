<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Illuminate\Auth\Access\Response;

class PermissionPolicy
{
    /**
     * Determine whether the user can view any permissions.
     * Only super admins can manage permissions
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can view a specific permission.
     * Only super admins can manage permissions
     */
    public function view(User $user, Permission $permission): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can create permissions.
     * Only super admins can manage permissions
     */
    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can update a permission.
     * Only super admins can manage permissions
     */
    public function update(User $user, Permission $permission): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can delete a permission.
     * Only super admins can manage permissions
     */
    public function delete(User $user, Permission $permission): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can restore permissions.
     * Only super admins can manage permissions
     */
    public function restore(User $user, Permission $permission): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can permanently delete permissions.
     * Only super admins can manage permissions
     */
    public function forceDelete(User $user, Permission $permission): bool
    {
        return $user->hasRole('super_admin');
    }
}
