<?php

namespace App\Services;

use App\Services\Contracts\RoleServiceContract;
use App\Services\AuditLogService;
use Spatie\Permission\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;


/**
 * Role Service
 *
 * Handles RBAC role management including creation, deletion, and permission assignment.
 */
class RoleService implements RoleServiceContract
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    /**
     * List all roles with optional filtering
     */
    public function listRoles(array $filters = []): LengthAwarePaginator
    {
        try {
            $query = Role::query();

            if (!empty($filters['search'])) {
                $query->where('name', 'like', '%' . $filters['search'] . '%');
            }

            if (!empty($filters['include_permissions'])) {
                $query->with('permissions');
            }

            return $query->latest()->paginate($filters['per_page'] ?? 50);
        } catch (\Exception $e) {
            Log::error('Failed to fetch roles', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create a new role
     */
    public function createRole(array $data): Role
    {
        try {
            $role = Role::create([
                'name' => $data['name'],
            ]);

            $this->auditLogService->log('create', Role::class, $role->id, null, $data, 'Role created');

            return $role;
        } catch (\Exception $e) {
            Log::error('Failed to create role', ['error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    /**
     * Get role with details
     */
    public function getRoleDetails(Role $role): array
    {
        try {
            $usersCount = $role->users()->count();
            return array_merge($role->load('permissions')->toArray(), ['users_count' => $usersCount]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch role details', ['error' => $e->getMessage(), 'role_id' => $role->id]);
            throw $e;
        }
    }

    /**
     * Update role
     */
    public function updateRole(Role $role, array $data): Role
    {
        try {
            $oldValues = $role->only(array_keys($data));
            $role->update($data);

            $this->auditLogService->log('update', Role::class, $role->id, $oldValues, $data, 'Role updated');

            return $role;
        } catch (\Exception $e) {
            Log::error('Failed to update role', ['error' => $e->getMessage(), 'role_id' => $role->id]);
            throw $e;
        }
    }

    /**
     * Delete role if no users assigned
     */
    public function deleteRole(Role $role): bool
    {
        try {
            if ($role->users()->exists()) {
                throw new \Exception('Cannot delete role that has assigned users');
            }

            $oldValues = $role->toArray();
            $role->delete();

            $this->auditLogService->log('delete', Role::class, $role->id, $oldValues, null, 'Role deleted');

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete role', ['error' => $e->getMessage(), 'role_id' => $role->id]);
            throw $e;
        }
    }

    /**
     * Assign permissions to role
     */
    public function assignPermissionsToRole(Role $role, array $permissionNames): array
    {
        try {
            $oldPermissions = $role->permissions->pluck('name')->toArray();

            $role->givePermissionTo($permissionNames);

            $newPermissions = $role->fresh()->permissions->pluck('name')->toArray();

            $this->auditLogService->log(
                'assign_permissions',
                Role::class,
                $role->id,
                ['permissions' => $oldPermissions],
                ['permissions' => $newPermissions],
                'Permissions assigned to role: ' . implode(', ', $permissionNames)
            );

            return [
                'role' => $role->name,
                'assigned_permissions' => $permissionNames,
                'current_permissions' => $newPermissions,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to assign permissions to role', [
                'error' => $e->getMessage(),
                'role_id' => $role->id,
                'permissions' => $permissionNames,
            ]);
            throw $e;
        }
    }

    /**
     * Remove permissions from role
     */
    public function removePermissionsFromRole(Role $role, array $permissionNames): array
    {
        try {
            $oldPermissions = $role->permissions->pluck('name')->toArray();

            $role->revokePermissionTo($permissionNames);

            $newPermissions = $role->fresh()->permissions->pluck('name')->toArray();

            $this->auditLogService->log(
                'remove_permissions',
                Role::class,
                $role->id,
                ['permissions' => $oldPermissions],
                ['permissions' => $newPermissions],
                'Permissions removed from role: ' . implode(', ', $permissionNames)
            );

            return [
                'role' => $role->name,
                'removed_permissions' => $permissionNames,
                'current_permissions' => $newPermissions,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to remove permissions from role', [
                'error' => $e->getMessage(),
                'role_id' => $role->id,
                'permissions' => $permissionNames,
            ]);
            throw $e;
        }
    }
}
