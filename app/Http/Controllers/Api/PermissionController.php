<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PermissionController extends Controller
{
    /**
     * Display a listing of permissions
     *
     * @group RBAC Management
     * @authenticated
     * @description List all permissions (Super Admin only)
     *
     * @queryParam search Search permissions by name. Example: manage_users
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "manage_users",
     *       "guard_name": "web",
     *       "created_at": "2025-01-01T00:00:00Z",
     *       "updated_at": "2025-01-01T00:00:00Z"
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Permission::class);

        $query = Permission::query();

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $permissions = $query->latest()->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    /**
     * Store a newly created permission
     *
     * @group RBAC Management
     * @authenticated
     * @description Create a new permission (Super Admin only)
     *
     * @bodyParam name string required The permission name. Example: view_secret_data
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Permission created successfully",
     *   "data": {
     *     "id": 1,
     *     "name": "view_secret_data",
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
     *     "name": ["The permission name is required"]
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Permission::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
        ]);

        $permission = Permission::create($validated);

        // Audit log
        $this->logAuditAction('create', Permission::class, $permission->id, null, $validated, 'Permission created');

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully',
            'data' => $permission,
        ], 201);
    }

    /**
     * Display the specified permission
     *
     * @group RBAC Management
     * @authenticated
     * @description Get detailed information about a specific permission (Super Admin only)
     *
     * @urlParam permission required The permission name
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "name": "manage_users",
     *     "guard_name": "web",
     *     "created_at": "2025-01-01T00:00:00Z",
     *     "updated_at": "2025-01-01T00:00:00Z",
     *     "roles": [
     *       {
     *         "id": 1,
     *         "name": "super_admin"
     *       }
     *     ]
     *   }
     * }
     */
    public function show(Permission $permission): JsonResponse
    {
        $this->authorize('view', $permission);

        return response()->json([
            'success' => true,
            'data' => $permission->load('roles'),
        ]);
    }

    /**
     * Update the specified permission
     *
     * @group RBAC Management
     * @authenticated
     * @description Update permission information (Super Admin only)
     *
     * @urlParam permission required The permission name
     * @bodyParam name string Update the permission name. Example: manage_all_users
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Permission updated successfully",
     *   "data": {
     *     "id": 1,
     *     "name": "manage_all_users",
     *     "guard_name": "web",
     *     "created_at": "2025-01-01T00:00:00Z",
     *     "updated_at": "2025-01-01T00:00:00Z"
     *   }
     * }
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $this->authorize('update', $permission);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:permissions,name,' . $permission->id,
        ]);

        $oldValues = $permission->only(array_keys($validated));

        $permission->update($validated);

        // Audit log
        $this->logAuditAction('update', Permission::class, $permission->id, $oldValues, $validated, 'Permission updated');

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully',
            'data' => $permission,
        ]);
    }

    /**
     * Remove the specified permission
     *
     * @group RBAC Management
     * @authenticated
     * @description Delete a permission (Super Admin only)
     *
     * @urlParam permission required The permission name
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Permission deleted successfully"
     * }
     *
     * @response 409 {
     *   "success": false,
     *   "message": "Cannot delete permission that is assigned to roles"
     * }
     */
    public function destroy(Permission $permission): JsonResponse
    {
        $this->authorize('delete', $permission);

        // Check if permission is assigned to any roles
        if ($permission->roles()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete permission that is assigned to roles',
            ], 409);
        }

        $oldValues = $permission->toArray();

        $permission->delete();

        // Audit log
        $this->logAuditAction('delete', Permission::class, $permission->id, $oldValues, null, 'Permission deleted');

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully',
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
            Log::error('Failed to create audit log for permission', [
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
