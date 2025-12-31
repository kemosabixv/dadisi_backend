<?php

namespace App\Services\Contracts;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * User Role Service Contract
 *
 * Defines the interface for role management operations including
 * role assignment, removal, and synchronization.
 *
 * @package App\Services\Contracts
 */
interface UserRoleServiceContract
{
    /**
     * Assign a single role to a user
     *
     * @param Authenticatable $actor The user performing the action
     * @param User $user The user to assign role to
     * @param string $role The role name
     * @return User The updated user with roles
     *
     * @throws \App\Exceptions\UserException
     */
    public function assignRole(Authenticatable $actor, User $user, string $role): User;

    /**
     * Remove a single role from a user
     *
     * @param Authenticatable $actor The user performing the action
     * @param User $user The user to remove role from
     * @param string $role The role name
     * @return User The updated user with roles
     *
     * @throws \App\Exceptions\UserException
     */
    public function removeRole(Authenticatable $actor, User $user, string $role): User;

    /**
     * Sync roles (replace all roles with new set)
     *
     * @param Authenticatable $actor The user performing the action
     * @param User $user The user to sync roles for
     * @param array $roles Array of role names
     * @return User The updated user with new roles
     *
     * @throws \App\Exceptions\UserException
     */
    public function syncRoles(Authenticatable $actor, User $user, array $roles): User;

    /**
     * Get all roles for a user
     *
     * @param User $user The user
     * @return \Illuminate\Support\Collection Collection of role names
     */
    public function getRoles(User $user): \Illuminate\Support\Collection;

    /**
     * Check if user has a specific role
     *
     * @param User $user The user
     * @param string $role The role name
     * @return bool True if user has the role
     */
    public function hasRole(User $user, string $role): bool;
}
