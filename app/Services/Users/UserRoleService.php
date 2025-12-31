<?php

namespace App\Services\Users;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Contracts\UserRoleServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * User Role Service
 *
 * Handles user role operations including assignment, removal,
 * and synchronization using Spatie permissions.
 *
 * @package App\Services\Users
 */
class UserRoleService implements UserRoleServiceContract
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
    public function assignRole(Authenticatable $actor, User $user, string $role): User
    {
        try {
            // Check if user already has role
            if ($user->hasRole($role)) {
                Log::info("User {$user->id} already has role {$role}");
                return $user->fresh();
            }

            $user->assignRole($role);

            // Log audit trail
            $this->logAudit($actor, "assign_role:{$role}", $user);

            Log::info("Role {$role} assigned to user {$user->id} by {$actor->getAuthIdentifier()}");

            return $user->fresh();
        } catch (\App\Exceptions\UserException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Failed to assign role: {$e->getMessage()}");
            throw new \App\Exceptions\UserException("Failed to assign role", 422, $e);
        }
    }

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
    public function removeRole(Authenticatable $actor, User $user, string $role): User
    {
        try {
            // Check if user has role
            if (!$user->hasRole($role)) {
                Log::info("User {$user->id} does not have role {$role}");
                return $user->fresh();
            }

            $user->removeRole($role);

            // Log audit trail
            $this->logAudit($actor, "remove_role:{$role}", $user);

            Log::info("Role {$role} removed from user {$user->id} by {$actor->getAuthIdentifier()}");

            return $user->fresh();
        } catch (\App\Exceptions\UserException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Failed to remove role: {$e->getMessage()}");
            throw new \App\Exceptions\UserException("Failed to remove role", 422, $e);
        }
    }

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
    public function syncRoles(Authenticatable $actor, User $user, array $roles): User
    {
        try {
            $user->syncRoles($roles);

            // Log audit trail
            $this->logAudit($actor, 'sync_roles:' . implode(',', $roles), $user);

            Log::info("Roles synced for user {$user->id} by {$actor->getAuthIdentifier()}: " . implode(',', $roles));

            return $user->fresh();
        } catch (\App\Exceptions\UserException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Failed to sync roles: {$e->getMessage()}");
            throw new \App\Exceptions\UserException("Failed to sync roles", 422, $e);
        }
    }

    /**
     * Get all roles for a user
     *
     * @param User $user The user
     * @return Collection Collection of role names
     */
    public function getRoles(User $user): Collection
    {
        return $user->getRoleNames();
    }

    /**
     * Check if user has a specific role
     *
     * @param User $user The user
     * @param string $role The role name
     * @return bool True if user has the role
     */
    public function hasRole(User $user, string $role): bool
    {
        return $user->hasRole($role);
    }

    /**
     * Log audit trail
     *
     * @param Authenticatable $actor The user performing the action
     * @param string $action The action performed
     * @param User $user The affected user
     * @return void
     */
    private function logAudit(Authenticatable $actor, string $action, User $user): void
    {
        try {
            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => $action,
                'model_type' => User::class,
                'model_id' => $user->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to log audit: {$e->getMessage()}");
        }
    }
}
