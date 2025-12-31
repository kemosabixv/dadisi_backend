<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Auth\Access\Response;

class RolePolicy
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
     * Determine whether the user can view any roles.
     * Only super admins can manage roles
     */
    public function viewAny(User $user): bool
    {
        return $user->can('manage_roles');
    }

    /**
     * Determine whether the user can view a specific role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can('manage_roles');
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->can('manage_roles');
    }

    /**
     * Determine whether the user can update a role.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can('manage_roles');
    }

    /**
     * Determine whether the user can delete a role.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->can('manage_roles');
    }

    /**
     * Determine whether the user can manage permissions for a role.
     */
    public function managePermissions(User $user, Role $role): bool
    {
        return $user->can('manage_permissions');
    }

    /**
     * Determine whether the user can restore roles.
     */
    public function restore(User $user, Role $role): bool
    {
        return $user->can('manage_roles');
    }

    /**
     * Determine whether the user can permanently delete roles.
     */
    public function forceDelete(User $user, Role $role): bool
    {
        return $user->can('manage_roles');
    }
}
