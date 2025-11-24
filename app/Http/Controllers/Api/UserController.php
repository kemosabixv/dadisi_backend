<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users (admin only)
     * Includes both active and soft-deleted users for admins
     *
     * @group User Management
     * @authenticated
     * @description List all users with filtering and search capabilities (Admin only)
     *
     * @queryParam include_deleted boolean Include soft-deleted users. Example: true
     * @queryParam search Search by username, email, or name. Example: john
     * @queryParam role Filter by role. Example: member
     * @queryParam status Filter by status (active, deleted). Example: active
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "data": [
     *       {
     *         "id": 1,
     *         "username": "johndoe",
     *         "email": "john@example.com",
     *         "roles": ["member"],
     *         "profile": {"first_name": "John", "last_name": "Doe"},
     *         "deleted_at": null
     *       }
     *     ],
     *     "current_page": 1,
     *     "total": 25
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::with(['memberProfile', 'roles'])
            ->withTrashed($request->boolean('include_deleted'));

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('memberProfile', function($profile) use ($search) {
                      $profile->where('first_name', 'like', "%{$search}%")
                             ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        // Role filter
        if ($request->has('role')) {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Status filter
        if ($request->has('status')) {
            if ($request->status === 'deleted') {
                $query->onlyTrashed();
            } elseif ($request->status === 'active') {
                $query->whereNull('deleted_at');
            }
        }

        $users = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Display the specified user
     *
     * @group User Management
     * @authenticated
     * @description Get detailed information about a specific user (Admin only)
     *
     * @urlParam id required The user ID
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "username": "johndoe",
     *     "email": "john@example.com",
     *     "email_verified_at": "2025-01-01T00:00:00Z",
     *     "roles": ["member"],
     *     "profile": {
     *       "first_name": "John",
     *       "last_name": "Doe",
     *       "county": {"name": "Nairobi"}
     *     },
     *     "last_login": "2025-01-01T00:00:00Z",
     *     "deleted_at": null
     *   }
     * }
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = User::withTrashed()
            ->with(['memberProfile.county', 'roles'])
            ->findOrFail($id);

        $this->authorize('view', $user);

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Update the specified user
     *
     * @group User Management
     * @authenticated
     * @description Update user information (Admin only)
     *
     * @urlParam id required The user ID
     * @bodyParam username string Update username. Example: newusername
     * @bodyParam email string Update email address. Example: newemail@example.com
     *
     * @response 200 {
     *   "success": true,
     *   "message": "User updated successfully",
     *   "data": {
     *     "id": 1,
     *     "username": "newusername",
     *     "email": "newemail@example.com"
     *   }
     * }
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $this->authorize('update', $user);

        $validated = $request->validate([
            'username' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users')->ignore($user->id)
            ],
        ]);

        $oldValues = $user->only(array_keys($validated));

        $user->update($validated);

        // Audit log
        $this->logAuditAction('update', User::class, $user->id, $oldValues, $validated, 'User information updated by admin');

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user->load(['memberProfile', 'roles']),
        ]);
    }

    /**
     * Soft delete the specified user
     *
     * @group User Management
     * @authenticated
     * @description Soft delete a user account (Admin or self)
     *
     * @urlParam id required The user ID
     *
     * @response 200 {
     *   "success": true,
     *   "message": "User account has been deactivated"
     * }
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $this->authorize('delete', $user);

        $oldValues = $user->toArray();

        // Revoke all tokens
        $user->tokens()->delete();

        // Soft delete user (this will cascade to profile due to foreign key)
        $user->delete();

        // Audit log
        $this->logAuditAction('delete', User::class, $user->id, $oldValues, null, 'User account soft deleted');

        return response()->json([
            'success' => true,
            'message' => 'User account has been deactivated',
        ]);
    }

    /**
     * Allow users to delete their own account
     *
     * @group User Management
     * @authenticated
     * @description Allow authenticated users to delete their own account
     *
     * @bodyParam password string required Current password confirmation. Example: mypassword123
     * @bodyParam reason string optional Reason for deletion. Example: No longer need account
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Your account has been deactivated. You have been logged out from all devices."
     * }
     */
    public function deleteSelf(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        // Verify password
        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 400);
        }

        $oldValues = $user->toArray();

        // Revoke all tokens (logout from all devices)
        $user->tokens()->delete();

        // Soft delete user
        $user->delete();

        // Audit log
        $this->logAuditAction('self_delete', User::class, $user->id, $oldValues, null,
            'User self-deleted account. Reason: ' . ($validated['reason'] ?? 'Not specified'));

        return response()->json([
            'success' => true,
            'message' => 'Your account has been deactivated. You have been logged out from all devices.',
        ]);
    }

    /**
     * Restore a soft-deleted user
     *
     * @group User Management
     * @authenticated
     * @description Restore a soft-deleted user account (Super Admin/Admin only)
     *
     * @urlParam id required The user ID to restore
     *
     * @response 200 {
     *   "success": true,
     *   "message": "User account has been restored",
     *   "data": {
     *     "id": 1,
     *     "username": "johndoe",
     *     "email": "john@example.com"
     *   }
     * }
     */
    public function restore(Request $request, string $id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $this->authorize('restore', $user);

        $user->restore();

        // Audit log
        $this->logAuditAction('restore', User::class, $user->id, null, $user->toArray(), 'User account restored by admin');

        return response()->json([
            'success' => true,
            'message' => 'User account has been restored',
            'data' => $user->load(['memberProfile', 'roles']),
        ]);
    }

    /**
     * Force delete a user (permanent deletion)
     *
     * @group User Management
     * @authenticated
     * @description Permanently delete a user after retention period (Super Admin only)
     *
     * @urlParam id required The user ID to permanently delete
     *
     * @response 200 {
     *   "success": true,
     *   "message": "User account permanently deleted"
     * }
     */
    public function forceDelete(Request $request, string $id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $user);

        // Check retention period from settings
        $retentionDays = \App\Models\UserDataRetentionSetting::getRetentionDays('user_accounts');
        if ($user->deleted_at && $user->deleted_at->addDays($retentionDays)->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => "Cannot permanently delete user before retention period expires ({$retentionDays} days)",
            ], 400);
        }

        $oldValues = $user->toArray();

        // Delete associated personal access tokens
        $user->tokens()->delete();

        // Force delete (permanent)
        $user->forceDelete();

        // Audit log
        $this->logAuditAction('force_delete', User::class, $user->id, $oldValues, null, 'User account permanently deleted');

        return response()->json([
            'success' => true,
            'message' => 'User account permanently deleted',
        ]);
    }

    /**
     * Get audit log for a specific user
     *
     * @group User Management
     * @authenticated
     * @description View audit trail for a specific user (Admin only)
     *
     * @urlParam id required The user ID
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "action": "update",
     *       "model_type": "User",
     *       "old_values": {"username": "oldname"},
     *       "new_values": {"username": "newname"},
     *       "user_id": 2,
     *       "created_at": "2025-01-01T00:00:00Z"
     *     }
     *   ]
     * }
     */
    public function auditLog(Request $request, string $id): JsonResponse
    {
        $this->authorize('viewAuditLogs', User::class);

        $logs = AuditLog::where('model_type', User::class)
            ->where('model_id', $id)
            ->with('user:id,username')
            ->latest()
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Export user data for GDPR compliance
     *
     * @group User Management
     * @authenticated
     * @description Export all user data for GDPR compliance
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "user": {...},
     *     "profile": {...},
     *     "audit_logs": [...],
     *     "exported_at": "2025-01-01T00:00:00Z"
     *   }
     * }
     */
    public function exportData(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'user' => $user->toArray(),
            'profile' => $user->memberProfile?->toArray(),
            'audit_logs' => AuditLog::where('model_type', User::class)
                ->where('model_id', $user->id)
                ->latest()
                ->get()
                ->toArray(),
            'exported_at' => now()->toISOString(),
        ];

        // Audit log the export
        $this->logAuditAction('export', User::class, $user->id, null, null, 'User data exported for GDPR compliance');

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Assign role to user
     *
     * @group RBAC Management
     * @authenticated
     * @description Assign a role to a user (Super Admin only)
     *
     * @urlParam id required The user ID
     * @bodyParam role string required Role to assign. Example: admin
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Role assigned successfully",
     *   "data": {
     *     "id": 1,
     *     "username": "johndoe",
     *     "roles": ["member", "admin"]
     *   }
     * }
     */
    public function assignRole(Request $request, string $id): JsonResponse
    {
        $this->authorize('assignRoles', User::class);

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $oldRoles = $user->roles->pluck('name')->toArray();

        $user->assignRole($validated['role']);

        // Audit log
        $this->logAuditAction('assign_role', User::class, $user->id,
            ['roles' => $oldRoles],
            ['roles' => $user->fresh()->roles->pluck('name')->toArray()],
            "Role '{$validated['role']}' assigned to user"
        );

        return response()->json([
            'success' => true,
            'message' => 'Role assigned successfully',
            'data' => $user->load('roles'),
        ]);
    }

    /**
     * Remove role from user
     *
     * @group User Management
     * @authenticated
     * @description Remove a role from a user (Super Admin only)
     *
     * @urlParam id required The user ID
     * @bodyParam role string required Role to remove. Example: admin
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Role removed successfully",
     *   "data": {
     *     "id": 1,
     *     "username": "johndoe",
     *     "roles": ["member"]
     *   }
     * }
     */
    public function removeRole(Request $request, string $id): JsonResponse
    {
        $this->authorize('assignRoles', User::class);

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $oldRoles = $user->roles->pluck('name')->toArray();

        $user->removeRole($validated['role']);

        // Audit log
        $this->logAuditAction('remove_role', User::class, $user->id,
            ['roles' => $oldRoles],
            ['roles' => $user->fresh()->roles->pluck('name')->toArray()],
            "Role '{$validated['role']}' removed from user"
        );

        return response()->json([
            'success' => true,
            'message' => 'Role removed successfully',
            'data' => $user->load('roles'),
        ]);
    }

    /**
     * Sync user roles (replace all roles)
     *
     * @group User Management
     * @authenticated
     * @description Replace all user roles with new set (Super Admin only)
     *
     * @urlParam id required The user ID
     * @bodyParam roles array required Array of role names. Example: ["member", "admin"]
     *
     * @response 200 {
     *   "success": true,
     *   "message": "User roles updated successfully",
     *   "data": {
     *     "id": 1,
     *     "username": "johndoe",
     *     "roles": ["member", "admin"]
     *   }
     * }
     */
    public function syncRoles(Request $request, string $id): JsonResponse
    {
        $this->authorize('assignRoles', User::class);

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        $oldRoles = $user->roles->pluck('name')->toArray();

        $user->syncRoles($validated['roles']);

        // Audit log
        $this->logAuditAction('sync_roles', User::class, $user->id,
            ['roles' => $oldRoles],
            ['roles' => $validated['roles']],
            'User roles synchronized'
        );

        return response()->json([
            'success' => true,
            'message' => 'User roles updated successfully',
            'data' => $user->load('roles'),
        ]);
    }

    /**
     * Bulk assign role to multiple users
     *
     * @group User Management
     * @authenticated
     * @description Assign a role to multiple users at once (Super Admin only)
     *
     * @bodyParam user_ids array required Array of user IDs. Example: [1, 2, 3]
     * @bodyParam role string required Role to assign. Example: admin
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Role assigned to 3 users successfully",
     *   "data": {
     *     "successful": 3,
     *     "failed": 0,
     *     "errors": []
     *   }
     * }
     */
    public function bulkAssignRole(Request $request): JsonResponse
    {
        $this->authorize('bulkOperations', User::class);

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'integer|exists:users,id',
            'role' => 'required|string|exists:roles,name',
        ]);

        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($validated['user_ids'] as $userId) {
            try {
                $user = User::findOrFail($userId);
                $oldRoles = $user->roles->pluck('name')->toArray();

                $user->assignRole($validated['role']);

                // Audit log
                $this->logAuditAction('bulk_assign_role', User::class, $user->id,
                    ['roles' => $oldRoles],
                    ['roles' => $user->fresh()->roles->pluck('name')->toArray()],
                    "Role '{$validated['role']}' assigned via bulk operation"
                );

                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "User {$userId}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Role assigned to {$successful} users successfully",
            'data' => [
                'successful' => $successful,
                'failed' => $failed,
                'errors' => $errors,
            ],
        ]);
    }

    /**
     * Bulk remove role from multiple users
     *
     * @group User Management
     * @authenticated
     * @description Remove a role from multiple users at once (Super Admin only)
     *
     * @bodyParam user_ids array required Array of user IDs. Example: [1, 2, 3]
     * @bodyParam role string required Role to remove. Example: admin
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Role removed from 3 users successfully",
     *   "data": {
     *     "successful": 3,
     *     "failed": 0,
     *     "errors": []
     *   }
     * }
     */
    public function bulkRemoveRole(Request $request): JsonResponse
    {
        $this->authorize('bulkOperations', User::class);

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'integer|exists:users,id',
            'role' => 'required|string|exists:roles,name',
        ]);

        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($validated['user_ids'] as $userId) {
            try {
                $user = User::findOrFail($userId);
                $oldRoles = $user->roles->pluck('name')->toArray();

                $user->removeRole($validated['role']);

                // Audit log
                $this->logAuditAction('bulk_remove_role', User::class, $user->id,
                    ['roles' => $oldRoles],
                    ['roles' => $user->fresh()->roles->pluck('name')->toArray()],
                    "Role '{$validated['role']}' removed via bulk operation"
                );

                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "User {$userId}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Role removed from {$successful} users successfully",
            'data' => [
                'successful' => $successful,
                'failed' => $failed,
                'errors' => $errors,
            ],
        ]);
    }

    /**
     * Bulk delete users
     *
     * @group User Management
     * @authenticated
     * @description Soft delete multiple users at once (Admin only)
     *
     * @bodyParam user_ids array required Array of user IDs. Example: [1, 2, 3]
     *
     * @response 200 {
     *   "success": true,
     *   "message": "3 users deleted successfully",
     *   "data": {
     *     "successful": 3,
     *     "failed": 0,
     *     "errors": []
     *   }
     * }
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $this->authorize('bulkOperations', User::class);

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:50',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($validated['user_ids'] as $userId) {
            try {
                $user = User::findOrFail($userId);
                $oldValues = $user->toArray();

                // Revoke all tokens
                $user->tokens()->delete();

                // Soft delete user
                $user->delete();

                // Audit log
                $this->logAuditAction('bulk_delete', User::class, $user->id, $oldValues, null, 'User account soft deleted via bulk operation');

                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "User {$userId}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$successful} users deleted successfully",
            'data' => [
                'successful' => $successful,
                'failed' => $failed,
                'errors' => $errors,
            ],
        ]);
    }

    /**
     * Bulk restore users
     *
     * @group User Management
     * @authenticated
     * @description Restore multiple soft-deleted users at once (Admin only)
     *
     * @bodyParam user_ids array required Array of user IDs. Example: [1, 2, 3]
     *
     * @response 200 {
     *   "success": true,
     *   "message": "3 users restored successfully",
     *   "data": {
     *     "successful": 3,
     *     "failed": 0,
     *     "errors": []
     *   }
     * }
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $this->authorize('bulkOperations', User::class);

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:50',
            'user_ids.*' => 'integer',
        ]);

        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($validated['user_ids'] as $userId) {
            try {
                $user = User::withTrashed()->findOrFail($userId);

                $user->restore();

                // Audit log
                $this->logAuditAction('bulk_restore', User::class, $user->id, null, $user->toArray(), 'User account restored via bulk operation');

                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "User {$userId}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$successful} users restored successfully",
            'data' => [
                'successful' => $successful,
                'failed' => $failed,
                'errors' => $errors,
            ],
        ]);
    }

    /**
     * Bulk update users
     *
     * @group User Management
     * @authenticated
     * @description Update multiple users with the same data (Admin only)
     *
     * @bodyParam user_ids array required Array of user IDs. Example: [1, 2, 3]
     * @bodyParam data object required Update data. Example: {"email_verified_at": "2025-01-01T00:00:00Z"}
     *
     * @response 200 {
     *   "success": true,
     *   "message": "3 users updated successfully",
     *   "data": {
     *     "successful": 3,
     *     "failed": 0,
     *     "errors": []
     *   }
     * }
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->authorize('bulkOperations', User::class);

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:50',
            'user_ids.*' => 'integer|exists:users,id',
            'data' => 'required|array',
            'data.username' => 'sometimes|string|max:255',
            'data.email' => 'sometimes|email',
        ]);

        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($validated['user_ids'] as $userId) {
            try {
                $user = User::withTrashed()->findOrFail($userId);
                $oldValues = $user->only(array_keys($validated['data']));

                $user->update($validated['data']);

                // Audit log
                $this->logAuditAction('bulk_update', User::class, $user->id, $oldValues, $validated['data'], 'User updated via bulk operation');

                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "User {$userId}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$successful} users updated successfully",
            'data' => [
                'successful' => $successful,
                'failed' => $failed,
                'errors' => $errors,
            ],
        ]);
    }

    /**
     * Log audit actions
     */
    private function logAuditAction(string $action, string $modelType, int $modelId, ?array $oldValues, ?array $newValues, ?string $notes = null): void
    {
        try {
            AuditLog::create([
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
            // Log audit failure but don't fail the main operation
            Log::error('Failed to create audit log', [
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
