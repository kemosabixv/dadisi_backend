<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CreateUserDTO;
use App\DTOs\UpdateUserDTO;
use App\Exceptions\UserException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssignRoleRequest;
use App\Http\Requests\Api\BulkAssignRoleRequest;
use App\Http\Requests\Api\BulkDeleteUserRequest;
use App\Http\Requests\Api\BulkInviteUserRequest;
use App\Http\Requests\Api\BulkRemoveRoleRequest;
use App\Http\Requests\Api\BulkRestoreUserRequest;
use App\Http\Requests\Api\BulkUpdateUserRequest;
use App\Http\Requests\Api\DeleteSelfRequest;
use App\Http\Requests\Api\InviteUserRequest;
use App\Http\Requests\Api\RemoveRoleRequest;
use App\Http\Requests\Api\SyncRolesRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Http\Requests\Api\UploadProfilePictureRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Contracts\UserBulkOperationServiceContract;
use App\Services\Contracts\UserInvitationServiceContract;
use App\Services\Contracts\UserRoleServiceContract;
use App\Services\Contracts\UserServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group User Management
 */
class UserController extends Controller
{
    /**
     * UserController constructor.
     */
    public function __construct(
        private UserServiceContract $userService,
        private UserRoleServiceContract $roleService,
        private UserBulkOperationServiceContract $bulkService,
        private UserInvitationServiceContract $invitationService
    ) {
        $this->middleware(['auth:sanctum', 'admin'])->except(['deleteSelf', 'uploadProfilePicture', 'exportData']);
        $this->middleware('auth:sanctum')->only(['deleteSelf', 'uploadProfilePicture', 'exportData']);
    }

    /**
     * List All Users
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'role', 'status', 'include_deleted', 'verified']);
            $perPage = min((int) $request->input('per_page', 20), 100);

            $users = $this->userService->listUsers($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => UserResource::collection($users),
                'pagination' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve users', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve users'], 500);
        }
    }

    /**
     * Create User
     *
     * Create a new user account with member profile.
     *
     * @authenticated
     * @bodyParam username string required Unique username. Example: johndoe
     * @bodyParam email string required User email. Example: john@example.com
     * @bodyParam password string required Password (min 8 chars). Example: SecurePass123
     * @bodyParam first_name string optional First name. Example: John
     * @bodyParam last_name string optional Last name. Example: Doe
     * @bodyParam roles array optional Role names to assign. Example: ["member"]
     * @response 201 {"success": true, "message": "User created successfully", "data": {...}}
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'username' => 'required|string|unique:users,username|min:3|max:50',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
                'first_name' => 'nullable|string|max:100',
                'last_name' => 'nullable|string|max:100',
                'is_staff' => 'nullable|boolean',
                'roles' => 'nullable|array',
                'roles.*' => 'string|exists:roles,name',
            ]);

            $dto = CreateUserDTO::fromArray([
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $profileData = [
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'is_staff' => $validated['is_staff'] ?? false,
            ];

            $user = $this->userService->create($request->user(), $dto, $profileData);

            // Assign roles if provided
            if (!empty($validated['roles'])) {
                $user->syncRoles($validated['roles']);
                $user->load('roles');
            }

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => new UserResource($user),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        } catch (\Exception $e) {
            Log::error('Failed to create user', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create user'], 500);
        }
    }

    /**
     * Get User Details
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = $this->userService->getById($id);
            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }

    /**
     * Update User
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        try {
            $user = $this->userService->getById($id);
            $dto = UpdateUserDTO::fromRequest($request);
            
            $updatedUser = $this->userService->update($request->user(), $user, $dto);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => new UserResource($updatedUser),
            ]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        } catch (\Exception $e) {
            Log::error('User update failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to update user'], 500);
        }
    }

    /**
     * Deactivate User (Soft Delete)
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = $this->userService->getById($id);
            $this->userService->delete($request->user(), $user);

            return response()->json([
                'success' => true,
                'message' => 'User account deactivated',
            ]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to deactivate user'], 500);
        }
    }

    /**
     * Restore User
     */
    public function restore(Request $request, string $id): JsonResponse
    {
        try {
            $user = User::withTrashed()->findOrFail($id);
            $restoredUser = $this->userService->restore($request->user(), $user);

            return response()->json([
                'success' => true,
                'message' => 'User account restored',
                'data' => new UserResource($restoredUser),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to restore user'], 500);
        }
    }

    /**
     * Permanently Delete User
     */
    public function forceDelete(Request $request, string $id): JsonResponse
    {
        try {
            $user = User::withTrashed()->findOrFail($id);
            $this->userService->forceDelete($request->user(), $user);

            return response()->json([
                'success' => true,
                'message' => 'User account permanently deleted',
            ]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to permanently delete user'], 500);
        }
    }

    /**
     * Upload Profile Picture
     *
     * Upload a new profile picture for the authenticated user.
     *
     * @group User Management
     * @authenticated
     * @responseFile status=200 storage/responses/profile-picture-upload.json
     */
    public function uploadProfilePicture(UploadProfilePictureRequest $request): JsonResponse
    {
        try {
            $url = $this->userService->uploadProfilePicture($request->user(), $request->file('image'));

            return response()->json([
                'success' => true,
                'message' => 'Profile picture updated',
                'data' => ['profile_picture_url' => $url],
            ]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }
    }

    /**
     * Delete Own Account
     */
    public function deleteSelf(DeleteSelfRequest $request): JsonResponse
    {
        try {
            $this->userService->deleteSelf(
                $request->user(),
                $request->input('password'),
                $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Your account has been deactivated',
            ]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }
    }

    /**
     * Export User Data
     */
    public function exportData(Request $request): JsonResponse
    {
        try {
            $data = $this->userService->exportData($request->user());

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to export user data', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);
            return response()->json(['success' => false, 'message' => 'Failed to export user data'], 500);
        }
    }

    /**
     * Assign Role
     */
    public function assignRole(AssignRoleRequest $request, string $id): JsonResponse
    {
        try {
            $user = $this->userService->getById($id);
            $updatedUser = $this->roleService->assignRole($request->user(), $user, $request->input('role'));

            return response()->json([
                'success' => true,
                'message' => 'Role assigned successfully',
                'data' => new UserResource($updatedUser),
            ]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }
    }

    /**
     * Remove Role
     */
    public function removeRole(RemoveRoleRequest $request, string $id): JsonResponse
    {
        try {
            $user = $this->userService->getById($id);
            $updatedUser = $this->roleService->removeRole($request->user(), $user, $request->input('role'));

            return response()->json([
                'success' => true,
                'message' => 'Role removed successfully',
                'data' => new UserResource($updatedUser),
            ]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }
    }

    /**
     * Sync Roles
     */
    public function syncRoles(SyncRolesRequest $request, string $id): JsonResponse
    {
        try {
            $user = $this->userService->getById($id);
            $updatedUser = $this->roleService->syncRoles($request->user(), $user, $request->input('roles'));

            return response()->json([
                'success' => true,
                'message' => 'Roles synchronized',
                'data' => new UserResource($updatedUser),
            ]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }
    }

    /**
     * Bulk Assign Role
     */
    public function bulkAssignRole(BulkAssignRoleRequest $request): JsonResponse
    {
        try {
            $count = $this->bulkService->bulkAssignRole(
                $request->user(),
                $request->input('user_ids'),
                $request->input('role')
            );

            return response()->json([
                'success' => true,
                'message' => "Role assigned to {$count} users",
                'data' => ['count' => $count],
            ]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }
    }

    /**
     * Bulk Remove Role
     */
    public function bulkRemoveRole(BulkRemoveRoleRequest $request): JsonResponse
    {
        try {
            $count = $this->bulkService->bulkRemoveRole(
                $request->user(),
                $request->input('user_ids'),
                $request->input('role')
            );

            return response()->json([
                'success' => true,
                'message' => "Role removed from {$count} users",
                'data' => ['count' => $count],
            ]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }
    }

    /**
     * Bulk Delete Users
     */
    public function bulkDelete(BulkDeleteUserRequest $request): JsonResponse
    {
        try {
            $count = $this->bulkService->bulkDelete($request->user(), $request->input('user_ids'));

            return response()->json([
                'success' => true,
                'message' => "{$count} users deactivated",
                'data' => ['count' => $count],
            ]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }
    }

    /**
     * Bulk Restore Users
     */
    public function bulkRestore(BulkRestoreUserRequest $request): JsonResponse
    {
        try {
            $count = $this->bulkService->bulkRestore($request->user(), $request->input('user_ids'));

            return response()->json([
                'success' => true,
                'message' => "{$count} users restored",
                'data' => ['count' => $count],
            ]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }
    }

    /**
     * Bulk Update Users
     */
    public function bulkUpdate(BulkUpdateUserRequest $request): JsonResponse
    {
        try {
            $count = $this->bulkService->bulkUpdate(
                $request->user(),
                $request->input('user_ids'),
                $request->input('data')
            );

            return response()->json([
                'success' => true,
                'message' => "{$count} users updated",
                'data' => ['count' => $count],
            ]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }
    }

    /**
     * Invite User
     */
    public function invite(InviteUserRequest $request): JsonResponse
    {
        try {
            $invitation = $this->invitationService->invite($request->user(), $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Invitation sent successfully',
                'data' => $invitation,
            ]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }
    }

    /**
     * Bulk Invite
     */
    public function bulkInvite(BulkInviteUserRequest $request): JsonResponse
    {
        try {
            $count = $this->invitationService->bulkInvite($request->user(), $request->input('invitations'));

            return response()->json([
                'success' => true,
                'message' => "{$count} invitations sent",
                'data' => ['count' => $count],
            ]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }
    }

    /**
     * Get Audit Logs
     */
    public function auditLog(string $id): JsonResponse
    {
        try {
            $user = $this->userService->getById($id);
            $logs = $this->userService->getAuditLog($user);

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
    }
}
