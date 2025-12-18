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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * List All Users (Admin Portal)
     *
     * Comprehensive user listing endpoint for administrative user management.
     * Provides paginated results with advanced filtering, search, and role-based access control.
     * Includes soft-deleted users for audit and restoration purposes. Essential for user administration,
     * role assignments, and monitoring user activity across the system.
     *
     * Access Requirements: super_admin, admin users only (enforced via UserPolicy)
     * Use Cases: User administration portal, role management interface, user activity monitoring
     *
     * @group User Management
     * @authenticated
     * @description Administrative user listing with advanced filtering and search capabilities. Requires admin privileges.
     *
     * @queryParam include_deleted boolean optional Include soft-deleted users in results. Useful for viewing deactivated accounts. Example: true
     * @queryParam search string optional Search across username, email, and name fields (first_name, last_name from profile). Case-insensitive partial matching. Example: john
     * @queryParam role string optional Filter users by specific role name. Must match existing role names from roles table. Example: member
     * @queryParam status string optional Filter by account status. Options: "active" (non-deleted users), "deleted" (soft-deleted users only). Example: active
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 1,
     *         "username": "johndoe",
     *         "email": "john@example.com",
     *         "email_verified_at": "2025-01-01T00:00:00Z",
     *         "roles": [
     *           {"id": 1, "name": "member", "description": "Regular member user"}
     *         ],
     *         "profile": {
     *           "id": 1,
     *           "first_name": "John",
     *           "last_name": "Doe",
     *           "county": {"id": 1, "name": "Nairobi"}
     *         },
     *         "deleted_at": null,
     *         "created_at": "2025-01-01T00:00:00Z"
     *       }
     *     ],
     *     "first_page_url": "http://localhost:8000/api/users?page=1",
     *     "from": 1,
     *     "last_page": 5,
     *     "last_page_url": "http://localhost:8000/api/users?page=5",
     *     "next_page_url": "http://localhost:8000/api/users?page=2",
     *     "path": "http://localhost:8000/api/users",
     *     "per_page": 20,
     *     "prev_page_url": null,
     *     "to": 20,
     *     "total": 100
     *   }
     * }
     *
     * @response 403 {"message": "This action is unauthorized."}
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
     * Get user details
     *
     * Retrieves full profile information, role assignments, and status for a specific user.
     * This endpoint is restricted to administrators for viewing other users' data.
     * Includes soft-deleted users if they exist.
     *
     * @group User Management
     * @authenticated
     *
     * @urlParam id required The unique ID of the user. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "username": "johndoe",
     *     "email": "john@example.com",
     *     "email_verified_at": "2025-01-01T00:00:00Z",
     *     "roles": [
     *       {"id": 2, "name": "member", "guard_name": "web"}
     *     ],
     *     "profile": {
     *       "first_name": "John",
     *       "last_name": "Doe",
     *       "county": {"name": "Nairobi"}
     *     },
     *     "last_login_at": "2025-01-01T00:00:00Z",
     *     "deleted_at": null
     *   }
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "User not found"
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
     * Update user details
     *
     * Modifies core user account information such as username and email.
     * RESTRICTED: Admin or Super Admin access required.
     * Changes are audit logged.
     *
     * @group User Management
     * @authenticated
     *
     * @urlParam id required The user ID to update. Example: 1
     * @bodyParam username string optional New username (must be unique). Example: newusername
     * @bodyParam email string optional New email address (must be unique). Example: newemail@example.com
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
     * @response 422 {
     *   "message": "The username has already been taken."
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
     * Deactivate user (Soft Delete)
     *
     * Temporarily deactivates a user account by setting a 'deleted_at' timestamp.
     * The user will no longer be able to log in, but their data is preserved until retention period expires.
     * Admin action or user self-deletion (via separate endpoint) triggers this state.
     *
     * @group User Management
     * @authenticated
     *
     * @urlParam id required The user ID to deactivate. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "User account has been deactivated"
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "Unauthorized"
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
     * Upload Profile Picture
     *
     * Uploads and updates the user's profile picture.
     * Replaces any existing picture.
     *
     * @group User Management
     * @authenticated
     *
     * @bodyParam image file required The image file (jpeg, png, jpg, gif, svg). Max 5MB.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Profile picture updated successfully",
     *   "data": {
     *     "profile_picture_url": "http://localhost:8000/storage/profile-pictures/avatar.jpg"
     *   }
     * }
     */
    public function uploadProfilePicture(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:5120', // 5MB
        ]);

        $file = $request->file('image');
        $filename = 'profile-' . $user->id . '-' . time() . '.' . $file->getClientOriginalExtension();

        // Delete old picture if exists
        if ($user->profile_picture_path && Storage::disk('public')->exists($user->profile_picture_path)) {
            Storage::disk('public')->delete($user->profile_picture_path);
        }

        // Store new file
        $path = $file->storeAs('profile-pictures', $filename, 'public');

        // Update user record
        $oldValues = $user->only(['profile_picture_path']);
        $user->update(['profile_picture_path' => $path]);

        // Audit log
        $this->logAuditAction('update_profile_picture', User::class, $user->id, $oldValues, ['profile_picture_path' => $path], 'User uploaded new profile picture');

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated successfully',
            'data' => [
                'profile_picture_url' => $user->profile_picture_url,
                'user' => $user->load(['memberProfile', 'roles']),
            ],
        ]);
    }

    /**
     * Delete own account
     *
     * Allows an authenticated user to permanently deactivate their own account.
     * Requires current password for security verification.
     * Logs the user out from all devices immediately.
     *
     * @group User Management
     * @authenticated
     *
     * @bodyParam password string required Current password for confirmation. Example: mypassword123
     * @bodyParam reason string optional Feedback/reason for leaving. Example: No longer need account
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Your account has been deactivated. You have been logged out from all devices."
     * }
     * @response 400 {
     *   "success": false,
     *   "message": "Current password is incorrect"
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
     * Restore deactivated user
     *
     * Reactivates a previously soft-deleted user account.
     * The user will regain access to their account and previous roles.
     * RESTRICTED: Super Admin or Admin only.
     *
     * @group User Management
     * @authenticated
     *
     * @urlParam id required The user ID to restore. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "User account has been restored",
     *   "data": {
     *     "id": 1,
     *     "username": "johndoe",
     *     "deleted_at": null
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
     * Grants a specific system role to a user.
     * Roles determine permissions within the application.
     * RESTRICTED: Super Admin access required.
     *
     * @group User Management
     * @authenticated
     *
     * @urlParam id required The user ID. Example: 1
     * @bodyParam role string required The role name to assign. Must be a valid existing role. Example: admin
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
     * Revokes a specific system role from a user.
     * If the user only has one role, removing it may restrict their access significantly.
     * RESTRICTED: Super Admin access required.
     *
     * @group User Management
     * @authenticated
     *
     * @urlParam id required The user ID. Example: 1
     * @bodyParam role string required The role name to remove. Example: admin
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Role removed successfully",
     *   "data": {
     *     "id": 1,
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
     * Sync user roles
     *
     * Replaces ALL current roles for a user with the provided list.
     * Any roles not in the provided array will be revoked.
     * RESTRICTED: Super Admin access required.
     *
     * @group User Management
     * @authenticated
     *
     * @urlParam id required The user ID. Example: 1
     * @bodyParam roles array required List of role names to assign. Example: ["member", "admin"]
     *
     * @response 200 {
     *   "success": true,
     *   "message": "User roles updated successfully",
     *   "data": {
     *     "id": 1,
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
     * Get all audit logs (Admin only)
     */
    public function bulkAuditLogs(Request $request): JsonResponse
    {
        $this->authorize('viewAuditLogs', User::class);

        $query = AuditLog::with('user:id,username,email');

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('model_type')) {
            $query->where('model_type', 'like', '%' . $request->model_type . '%');
        }

        $logs = $query->latest()->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Invite a new user
     */
    public function invite(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'username' => 'required|string|max:255|unique:users,username',
            'roles' => 'sometimes|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        // Placeholder: In a real app, send invitation email
        $user = User::create([
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make(Str::random(12)),
        ]);

        if (isset($validated['roles']) && !empty($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        } else {
            $user->assignRole('member');
        }

        $this->logAuditAction('invite', User::class, $user->id, null, $validated, 'User invited');

        return response()->json([
            'success' => true,
            'message' => 'User invited successfully',
            'data' => $user->load('roles'),
        ]);
    }

    /**
     * Bulk invite users
     */
    public function bulkInvite(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'users' => 'required|array|min:1|max:50',
            'users.*.email' => 'required|email',
            'users.*.username' => 'required|string',
            'users.*.roles' => 'sometimes|array',
            'users.*.roles.*' => 'string|exists:roles,name',
        ]);

        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($validated['users'] as $inviteData) {
            try {
                // Check if user already exists
                if (User::where('email', $inviteData['email'])->exists()) {
                    throw new \Exception("User with email {$inviteData['email']} already exists");
                }

                $user = User::create([
                    'username' => $inviteData['username'],
                    'email' => $inviteData['email'],
                    'password' => Hash::make(Str::random(12)),
                ]);

                if (isset($inviteData['roles']) && !empty($inviteData['roles'])) {
                    $user->syncRoles($inviteData['roles']);
                } else {
                    $user->assignRole('member');
                }

                $this->logAuditAction('bulk_invite', User::class, $user->id, null, $inviteData, 'User invited via bulk operation');
                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Invite for {$inviteData['email']} failed: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully invited {$successful} users",
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
