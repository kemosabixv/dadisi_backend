<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Auth\Access\Response;

class RolePolicy
{
    /**
     * Determine whether the user can view any roles.
     * Only super admins can manage roles
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can view a specific role.
     * Only super admins can manage roles
     */
    public function view(User $user, Role $role): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can create roles.
     * Only super admins can manage roles
     */
    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can update a role.
     * Only super admins can manage roles
     */
    public function update(User $user, Role $role): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can delete a role.
     * Only super admins can manage roles
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can manage permissions for a role.
     * Only super admins can manage roles and their permissions
     */
    public function managePermissions(User $user, Role $role): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can restore roles.
     * Only super admins can manage roles
     */
    public function restore(User $user, Role $role): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can permanently delete roles.
     * Only super admins can manage roles
     */
    public function forceDelete(User $user, Role $role): bool
    {
        return $user->hasRole('super_admin');
    }
}
