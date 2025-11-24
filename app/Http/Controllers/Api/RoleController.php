<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    /**
     * Display a listing of roles
     *
     * @group RBAC Management
     * @authenticated
     * @description List all roles with their permissions (Super Admin only)
     *
     * @queryParam search Search roles by name. Example: admin
     * @queryParam include_permissions Include permissions for each role. Example: true
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "super_admin",
     *       "guard_name": "web",
     *       "created_at": "2025-01-01T00:00:00Z",
     *       "updated_at": "2025-01-01T00:00:00Z",
     *       "permissions": [
     *         {
     *           "id": 1,
     *           "name": "manage_users"
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $query = Role::query();

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $includePermissions = $request->boolean('include_permissions');

        if ($includePermissions) {
            $query->with('permissions');
        }

        $roles = $query->latest()->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Store a newly created role
     *
     * @group RBAC Management
     * @authenticated
     * @description Create a new role (Super Admin only)
     *
     * @bodyParam name string required The role name. Example: moderator
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Role created successfully",
     *   "data": {
     *     "id": 2,
     *     "name": "moderator",
     *     "guard_name": "web",
     *     "created_at": "2025-01-01T00:00:00Z",
     *     "updated_at": "2025-01-01T00:00:00Z"
     *   }
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "Validation failed",
     *   "errors": {
     *     "name": ["The role name is required"]
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
        ]);

        $role = Role::create($validated);

        // Audit log
        $this->logAuditAction('create', Role::class, $role->id, null, $validated, 'Role created');

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role,
        ], 201);
    }

    /**
     * Display the specified role
     *
     * @group RBAC Management
     * @authenticated
     * @description Get detailed information about a specific role (Super Admin only)
     *
     * @urlParam role required The role name
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "name": "super_admin",
     *     "guard_name": "web",
     *     "created_at": "2025-01-01T00:00:00Z",
     *     "updated_at": "2025-01-01T00:00:00Z",
     *     "permissions": [
     *       {
     *         "id": 1,
     *         "name": "manage_users"
     *       }
     *     ],
     *     "users_count": 5
     *   }
     * }
     */
    public function show(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        $usersCount = $role->users()->count();

        return response()->json([
            'success' => true,
            'data' => array_merge($role->load('permissions')->toArray(), ['users_count' => $usersCount]),
        ]);
    }

    /**
     * Update the specified role
     *
     * @group RBAC Management
     * @authenticated
     * @description Update role information (Super Admin only)
     *
     * @urlParam role required The role name
     * @bodyParam name string Update the role name. Example: senior_admin
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Role updated successfully",
     *   "data": {
     *     "id": 1,
     *     "name": "senior_admin",
     *     "guard_name": "web",
     *     "created_at": "2025-01-01T00:00:00Z",
     *     "updated_at": "2025-01-01T00:00:00Z"
     *   }
     * }
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:roles,name,' . $role->id,
        ]);

        $oldValues = $role->only(array_keys($validated));

        $role->update($validated);

        // Audit log
        $this->logAuditAction('update', Role::class, $role->id, $oldValues, $validated, 'Role updated');

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role,
        ]);
    }

    /**
     * Remove the specified role
     *
     * @group RBAC Management
     * @authenticated
     * @description Delete a role (Super Admin only)
     *
     * @urlParam role required The role name
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Role deleted successfully"
     * }
     *
     * @response 409 {
     *   "success": false,
     *   "message": "Cannot delete role that has assigned users"
     * }
     */
    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        // Check if role has assigned users
        if ($role->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete role that has assigned users',
            ], 409);
        }

        $oldValues = $role->toArray();

        $role->delete();

        // Audit log
        $this->logAuditAction('delete', Role::class, $role->id, $oldValues, null, 'Role deleted');

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Assign permissions to a role
     *
     * @group RBAC Management
     * @authenticated
     * @description Assign permissions to a role (Super Admin only)
     *
     * @urlParam role required The role name
     * @bodyParam permissions array required Array of permission names to assign. Example: ["manage_users", "view_reports"]
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Permissions assigned to role successfully",
     *   "data": {
     *     "role": "admin",
     *     "assigned_permissions": ["manage_users", "view_reports"],
     *     "current_permissions": ["manage_users", "view_reports", "create_events"]
     *   }
     * }
     */
    public function assignPermissions(Request $request, Role $role): JsonResponse
    {
        $this->authorize('managePermissions', $role);

        $validated = $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $oldPermissions = $role->permissions->pluck('name')->toArray();

        $role->givePermissionTo($validated['permissions']);

        $newPermissions = $role->fresh()->permissions->pluck('name')->toArray();

        // Audit log
        $this->logAuditAction('assign_permissions', Role::class, $role->id,
            ['permissions' => $oldPermissions],
            ['permissions' => $newPermissions],
            'Permissions assigned to role: ' . implode(', ', $validated['permissions'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned to role successfully',
            'data' => [
                'role' => $role->name,
                'assigned_permissions' => $validated['permissions'],
                'current_permissions' => $newPermissions,
            ],
        ]);
    }

    /**
     * Remove permissions from a role
     *
     * @group RBAC Management
     * @authenticated
     * @description Remove permissions from a role (Super Admin only)
     *
     * @urlParam role required The role name
     * @bodyParam permissions array required Array of permission names to remove. Example: ["manage_users"]
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Permissions removed from role successfully",
     *   "data": {
     *     "role": "admin",
     *     "removed_permissions": ["manage_users"],
     *     "current_permissions": ["view_reports", "create_events"]
     *   }
     * }
     */
    public function removePermissions(Request $request, Role $role): JsonResponse
    {
        $this->authorize('managePermissions', $role);

        $validated = $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $oldPermissions = $role->permissions->pluck('name')->toArray();

        $role->revokePermissionTo($validated['permissions']);

        $newPermissions = $role->fresh()->permissions->pluck('name')->toArray();

        // Audit log
        $this->logAuditAction('remove_permissions', Role::class, $role->id,
            ['permissions' => $oldPermissions],
            ['permissions' => $newPermissions],
            'Permissions removed from role: ' . implode(', ', $validated['permissions'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Permissions removed from role successfully',
            'data' => [
                'role' => $role->name,
                'removed_permissions' => $validated['permissions'],
                'current_permissions' => $newPermissions,
            ],
        ]);
    }

    /**
     * Log audit actions
     */
    private function logAuditAction(string $action, string $modelType, int $modelId, ?array $oldValues, ?array $newValues, ?string $notes = null): void
    {
        try {
            \App\Models\AuditLog::create([
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'user_id' => auth()->id(),
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'notes' => $notes,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create audit log for role', [
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
