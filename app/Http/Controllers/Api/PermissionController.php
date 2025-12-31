<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PermissionException;
use App\Http\Controllers\Controller;
use App\Services\Contracts\PermissionServiceContract;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Access\AuthorizationException;

class PermissionController extends Controller
{
    public function __construct(
        private PermissionServiceContract $permissionService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * List System Permissions
     *
     * Retrieves a paginated list of all registered system permissions.
     * These permissions define the granular actions available within the application (e.g., 'create_post', 'delete_user').
     *
     * @group RBAC Management
     * @groupDescription Administrative endpoints for managing Role-Based Access Control (RBAC). Use these to define roles and assigning specific permissions to them.
     * @authenticated
     * @description List all permissions (Super Admin only)
     *
     * @queryParam search string optional Filter permissions by name. Example: manage_users
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
        try {
            $this->authorize('viewAny', Permission::class);

            $filters = [
                'search' => $request->input('search'),
            ];

            $permissions = $this->permissionService->listPermissions($filters, 50);

            return response()->json([
                'success' => true,
                'data' => $permissions,
            ]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to fetch permissions', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch permissions'], 500);
        }
    }



    /**
     * Get Permission Details
     *
     * Retrieves detailed information about a specific permission.
     * Includes a list of roles that currently hold this permission.
     *
     * @group RBAC Management
     * @authenticated
     * @description Get detailed information about a specific permission (Super Admin only)
     *
     * @urlParam permission string required The unique identifier (name) of the permission.
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
        try {
            $this->authorize('view', $permission);

            return response()->json([
                'success' => true,
                'data' => $permission->load('roles'),
            ]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to fetch permission', ['error' => $e->getMessage(), 'permission_id' => $permission->id]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch permission'], 500);
        }
    }





    /**
     * Get Role Permissions
     *
     * Retrieves all permissions assigned to a specific role.
     *
     * @group RBAC Management
     * @authenticated
     *
     * @urlParam role_name string required The name of the role. Example: admin
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {"id": 1, "name": "manage_users"},
     *     {"id": 2, "name": "view_reports"}
     *   ]
     * }
     */
    public function getRolePermissions(string $roleName): JsonResponse
    {
        try {
            $permissions = $this->permissionService->getRolePermissions($roleName);

            return response()->json([
                'success' => true,
                'data' => $permissions,
            ]);
        } catch (PermissionException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Failed to get role permissions', ['error' => $e->getMessage(), 'role' => $roleName]);
            return response()->json(['success' => false, 'message' => 'Failed to get role permissions'], 500);
        }
    }
}
