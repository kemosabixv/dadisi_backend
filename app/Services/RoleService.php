<?php

namespace App\Services;

use App\DTOs\CreateRoleDTO;
use App\DTOs\UpdateRoleDTO;
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
            $query = Role::query()
                ->select('roles.*')
                ->where('guard_name', 'web')
                ->where('name', '!=', 'member'); // Hide built-in 'member' role

            if (!empty($filters['search'])) {
                $query->where('name', 'like', '%' . $filters['search'] . '%');
            }

            if (!empty($filters['include_permissions'])) {
                $query->with('permissions');
            }

            // Use the morph alias if defined in AppServiceProvider
            $userModel = \App\Models\User::class;
            $modelType = \Illuminate\Database\Eloquent\Relations\Relation::getMorphAlias($userModel) ?? $userModel;

            // Use a raw subquery for user counts instead of withCount('users')
            // This avoids the Spatie Permission package's users() relationship issue
            $query->addSelect(\Illuminate\Support\Facades\DB::raw('(
                SELECT COUNT(*)
                FROM model_has_roles
                WHERE model_has_roles.role_id = roles.id
                AND model_has_roles.model_type = \'' . addslashes($modelType) . '\'
            ) as users_count'));

            return $query->latest()->paginate($filters['per_page'] ?? 50);
        } catch (\Exception $e) {
            Log::error('Failed to fetch roles', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create a new role
     */
    public function createRole(CreateRoleDTO $dto): Role
    {
        try {
            $data = $dto->toArray();
            $role = Role::create([
                'name' => $data['name'],
            ]);

            $this->auditLogService->log('create', Role::class, $role->id, null, $data, 'Role created');

            return $role;
        } catch (\Exception $e) {
            Log::error('Failed to create role', ['error' => $e->getMessage(), 'data' => $dto->toArray()]);
            throw $e;
        }
    }

    /**
     * Get role with details
     */
    public function getRoleDetails(Role $role): array
    {
        try {
            $userModel = \App\Models\User::class;
            $modelType = \Illuminate\Database\Eloquent\Relations\Relation::getMorphAlias($userModel) ?? $userModel;

            $usersCount = \Illuminate\Support\Facades\DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', $modelType)
                ->count();

            return array_merge($role->load('permissions')->toArray(), ['users_count' => $usersCount]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch role details', ['error' => $e->getMessage(), 'role_id' => $role->id]);
            throw $e;
        }
    }

    /**
     * Update role
     */
    public function updateRole(Role $role, UpdateRoleDTO $dto): Role
    {
        try {
            if ($role->is_immutable) {
                throw new \Exception("The '{$role->name}' role is a protected system role and cannot be modified.");
            }

            $data = $dto->toArray();
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
            if ($role->is_immutable) {
                throw new \Exception("The '{$role->name}' role is a protected system role and cannot be deleted.");
            }

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
            // Enforcement: staff role MUST have access_admin_panel
            if ($role->name === 'staff' && in_array('access_admin_panel', $permissionNames)) {
                throw new \Exception("The 'access_admin_panel' permission is mandatory for the 'staff' role.");
            }

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
