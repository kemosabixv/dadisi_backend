<?php

namespace App\Services\Permissions;

use App\Exceptions\PermissionException;
use App\Models\AuditLog;
use App\Services\Contracts\PermissionServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * PermissionService
 *
 * Handles permission management using Spatie Permissions package.
 */
class PermissionService implements PermissionServiceContract
{


    /**
     * Grant permission to role
     *
     * @param Authenticatable $actor
     * @param string $roleName
     * @param string $permissionName
     * @return bool
     *
     * @throws PermissionException
     */
    public function grantPermissionToRole(Authenticatable $actor, string $roleName, string $permissionName): bool
    {
        try {
            return DB::transaction(function () use ($actor, $roleName, $permissionName) {
                $role = Role::findByName($roleName);
                $permission = Permission::findByName($permissionName);

                $role->givePermissionTo($permission);

                AuditLog::create([
                    'actor_id' => $actor->getAuthIdentifier(),
                    'action' => 'granted_permission',
                    'model_type' => Role::class,
                    'model_id' => $role->id,
                    'new_values' => ['permission' => $permissionName],
                ]);

                Log::info('Permission granted to role', [
                    'role' => $roleName,
                    'permission' => $permissionName,
                    'granted_by' => $actor->getAuthIdentifier(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            throw PermissionException::grantFailed($e->getMessage());
        }
    }

    /**
     * Revoke permission from role - overloaded to handle two signatures
     * Can be called as: revokePermissionFromRole(roleName, permissionName)
     * or: revokePermissionFromRole(actor, roleName, permissionName)
     *
     * @return bool
     *
     * @throws PermissionException
     */
    public function revokePermissionFromRole(...$args): bool
    {
        try {
            // Handle both signatures
            if (count($args) === 2) {
                // Called as revokePermissionFromRole(roleName, permissionName)
                [$roleName, $permissionName] = $args;
                $actor = null;
            } elseif (count($args) === 3) {
                // Called as revokePermissionFromRole(actor, roleName, permissionName)
                [$actor, $roleName, $permissionName] = $args;
            } else {
                throw new PermissionException("Invalid number of arguments");
            }

            return DB::transaction(function () use ($actor, $roleName, $permissionName) {
                $role = Role::findByName($roleName);
                $permission = Permission::findByName($permissionName);

                $role->revokePermissionTo($permission);

                if ($actor instanceof Authenticatable) {
                    AuditLog::create([
                        'actor_id' => $actor->getAuthIdentifier(),
                        'action' => 'revoked_permission',
                        'model_type' => Role::class,
                        'model_id' => $role->id,
                        'new_values' => ['permission' => $permissionName],
                    ]);
                }

                Log::info('Permission revoked from role', [
                    'role' => $roleName,
                    'permission' => $permissionName,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            throw PermissionException::revokeFailed($e->getMessage());
        }
    }

    /**
     * List permissions
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function listPermissions(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Permission::query();

        if (isset($filters['search']) && $filters['search']) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        return $query->orderBy('name', 'asc')->paginate($perPage);
    }

    /**
     * Get role permissions
     *
     * @param string $roleName
     * @return \Illuminate\Database\Eloquent\Collection
     *
     * @throws PermissionException
     */
    public function getRolePermissions(string $roleName): \Illuminate\Database\Eloquent\Collection
    {
        try {
            $role = Role::findByName($roleName);
            return $role->permissions;
        } catch (\Exception $e) {
            throw PermissionException::roleNotFound($roleName);
        }
    }

    /**
     * Assign role to user
     *
     * @param Authenticatable $actor
     * @param Authenticatable $user
     * @param string $roleName
     * @return bool
     *
     * @throws PermissionException
     */
    public function assignRole(Authenticatable $actor, Authenticatable $user, string $roleName): bool
    {
        try {
            return DB::transaction(function () use ($actor, $user, $roleName) {
                Role::findByName($roleName); // Verify role exists
                $user->assignRole($roleName);

                AuditLog::create([
                    'actor_id' => $actor->getAuthIdentifier(),
                    'action' => 'assigned_role',
                    'model_type' => $user::class,
                    'model_id' => $user->getAuthIdentifier(),
                    'new_values' => ['role' => $roleName],
                ]);

                Log::info('Role assigned to user', [
                    'actor_id' => $user->getAuthIdentifier(),
                    'role' => $roleName,
                    'assigned_by' => $actor->getAuthIdentifier(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            throw PermissionException::assignmentFailed($e->getMessage());
        }
    }

    /**
     * Revoke role from user
     *
     * @param Authenticatable $actor
     * @param Authenticatable $user
     * @param string $roleName
     * @return bool
     *
     * @throws PermissionException
     */
    public function revokeRole(Authenticatable $actor, Authenticatable $user, string $roleName): bool
    {
        try {
            return DB::transaction(function () use ($actor, $user, $roleName) {
                if (!$user->hasRole($roleName)) {
                    throw new PermissionException("User does not have role: {$roleName}");
                }

                $user->removeRole($roleName);

                AuditLog::create([
                    'actor_id' => $actor->getAuthIdentifier(),
                    'action' => 'revoked_role',
                    'model_type' => $user::class,
                    'model_id' => $user->getAuthIdentifier(),
                    'new_values' => ['role' => $roleName],
                ]);

                Log::info('Role revoked from user', [
                    'actor_id' => $user->getAuthIdentifier(),
                    'role' => $roleName,
                    'revoked_by' => $actor->getAuthIdentifier(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            throw PermissionException::revokeFailed($e->getMessage());
        }
    }

    /**
     * Assign permission to user
     *
     * @param Authenticatable $actor
     * @param Authenticatable $user
     * @param string $permissionName
     * @return bool
     *
     * @throws PermissionException
     */
    public function assignPermission(Authenticatable $actor, Authenticatable $user, string $permissionName): bool
    {
        try {
            return DB::transaction(function () use ($actor, $user, $permissionName) {
                Permission::findByName($permissionName); // Verify permission exists
                $user->givePermissionTo($permissionName);

                AuditLog::create([
                    'actor_id' => $actor->getAuthIdentifier(),
                    'action' => 'assigned_permission',
                    'model_type' => $user::class,
                    'model_id' => $user->getAuthIdentifier(),
                    'new_values' => ['permission' => $permissionName],
                ]);

                Log::info('Permission assigned to user', [
                    'actor_id' => $user->getAuthIdentifier(),
                    'permission' => $permissionName,
                    'assigned_by' => $actor->getAuthIdentifier(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            throw PermissionException::assignmentFailed($e->getMessage());
        }
    }

    /**
     * Revoke permission from user
     *
     * @param Authenticatable $actor
     * @param Authenticatable $user
     * @param string $permissionName
     * @return bool
     *
     * @throws PermissionException
     */
    public function revokePermission(Authenticatable $actor, Authenticatable $user, string $permissionName): bool
    {
        try {
            return DB::transaction(function () use ($actor, $user, $permissionName) {
                if (!$user->hasPermissionTo($permissionName)) {
                    throw new PermissionException("User does not have permission: {$permissionName}");
                }

                $user->revokePermissionTo($permissionName);

                AuditLog::create([
                    'actor_id' => $actor->getAuthIdentifier(),
                    'action' => 'revoked_permission',
                    'model_type' => $user::class,
                    'model_id' => $user->getAuthIdentifier(),
                    'new_values' => ['permission' => $permissionName],
                ]);

                Log::info('Permission revoked from user', [
                    'actor_id' => $user->getAuthIdentifier(),
                    'permission' => $permissionName,
                    'revoked_by' => $actor->getAuthIdentifier(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            throw PermissionException::revokeFailed($e->getMessage());
        }
    }

    /**
     * Assign permission to role (alias for grantPermissionToRole for backwards compatibility)
     *
     * @param string $roleName
     * @param string $permissionName
     * @return bool
     *
     * @throws PermissionException
     */
    public function givePermissionToRole(string $roleName, string $permissionName): bool
    {
        try {
            $role = Role::findByName($roleName);
            $permission = Permission::findByName($permissionName);

            $role->givePermissionTo($permission);

            Log::info('Permission given to role', [
                'role' => $roleName,
                'permission' => $permissionName,
            ]);

            return true;
        } catch (\Exception $e) {
            throw PermissionException::assignmentFailed($e->getMessage());
        }
    }

    /**
     * Sync user roles
     *
     * @param Authenticatable $actor
     * @param Authenticatable $user
     * @param array $roleNames
     * @return bool
     *
     * @throws PermissionException
     */
    public function syncRoles(Authenticatable $actor, Authenticatable $user, array $roleNames): bool
    {
        try {
            return DB::transaction(function () use ($actor, $user, $roleNames) {
                // Create roles that don't exist
                foreach ($roleNames as $roleName) {
                    if (!Role::where('name', $roleName)->exists()) {
                        Role::create(['name' => $roleName, 'guard_name' => 'web']);
                    }
                }

                $user->syncRoles($roleNames);

                AuditLog::create([
                    'actor_id' => $actor->getAuthIdentifier(),
                    'action' => 'synced_roles',
                    'model_type' => $user::class,
                    'model_id' => $user->getAuthIdentifier(),
                    'new_values' => ['roles' => $roleNames],
                ]);

                Log::info('User roles synced', [
                    'actor_id' => $user->getAuthIdentifier(),
                    'roles' => $roleNames,
                    'synced_by' => $actor->getAuthIdentifier(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            throw PermissionException::assignmentFailed($e->getMessage());
        }
    }

    /**
     * Sync user permissions
     *
     * @param Authenticatable $actor
     * @param Authenticatable $user
     * @param array $permissionNames
     * @return bool
     *
     * @throws PermissionException
     */
    public function syncPermissions(Authenticatable $actor, Authenticatable $user, array $permissionNames): bool
    {
        try {
            return DB::transaction(function () use ($actor, $user, $permissionNames) {
                $user->syncPermissions($permissionNames);

                AuditLog::create([
                    'actor_id' => $actor->getAuthIdentifier(),
                    'action' => 'synced_permissions',
                    'model_type' => $user::class,
                    'model_id' => $user->getAuthIdentifier(),
                    'new_values' => ['permissions' => $permissionNames],
                ]);

                Log::info('User permissions synced', [
                    'actor_id' => $user->getAuthIdentifier(),
                    'permissions' => $permissionNames,
                    'synced_by' => $actor->getAuthIdentifier(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            throw PermissionException::assignmentFailed($e->getMessage());
        }
    }

    /**
     * Get user roles
     *
     * @param Authenticatable $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserRoles(Authenticatable $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->roles;
    }

    /**
     * Get user permissions
     *
     * @param Authenticatable $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserPermissions(Authenticatable $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->getDirectPermissions();
    }

    /**
     * Get all roles
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllRoles(): \Illuminate\Database\Eloquent\Collection
    {
        return Role::all();
    }

    /**
     * Get all permissions
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllPermissions(): \Illuminate\Database\Eloquent\Collection
    {
        return Permission::all();
    }
}
