<?php

namespace App\Services\Users;

use App\DTOs\CreateMemberProfileDTO;
use App\DTOs\CreateUserDTO;
use App\DTOs\UpdateMemberProfileDTO;
use App\DTOs\UpdateUserDTO;
use App\Exceptions\UserException;
use App\Models\AuditLog;
use App\Models\County;
use App\Models\MemberProfile;
use App\Models\User;
use App\Services\Contracts\UserServiceContract;
use App\Services\Media\MediaService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * User Service
 *
 * Handles user management operations including CRUD operations,
 * profile management, and account lifecycle.
 */
class UserService implements UserServiceContract
{
    /**
     * @param  Authenticatable  $actor  The user performing the action
     * @param  CreateUserDTO  $dto  User creation data
     * @param  array  $profileData  Optional member profile data
     */
    public function __construct(
        private MediaService $mediaService
    ) {}

    /**
     * @param  Authenticatable  $actor  The user performing the action
     * @param  CreateUserDTO  $dto  User creation data
     * @param  array  $profileData  Optional member profile data
     * @return User The created user
     *
     * @throws UserException
     */
    public function create(Authenticatable $actor, CreateUserDTO $dto, array $profileData = []): User
    {
        try {
            $user = User::create($dto->toArray());

            // Create member profile automatically
            MemberProfile::create([
                'user_id' => $user->id,
                'first_name' => $profileData['first_name'] ?? '',
                'last_name' => $profileData['last_name'] ?? '',
            ]);

            // Load relationships
            $user->load(['memberProfile', 'roles']);

            // Log audit trail
            $this->logAudit($actor, 'create_user', $user);

            Log::info("User created by {$actor->getAuthIdentifier()}: {$user->id}");

            return $user;
        } catch (\Exception $e) {
            Log::error("Failed to create user: {$e->getMessage()}");
            throw UserException::creationFailed($e->getMessage());
        }
    }

    /**
     * Update an existing user
     *
     * @param  Authenticatable  $actor  The user performing the action
     * @param  User  $user  The user to update
     * @param  UpdateUserDTO  $dto  Updated user data
     * @return User The updated user
     *
     * @throws UserException
     */
    public function update(Authenticatable $actor, User $user, UpdateUserDTO $dto): User
    {
        try {
            $updateData = $dto->toArray();

            if (! empty($updateData)) {
                $user->update($updateData);
            }

            // Log audit trail
            $this->logAudit($actor, 'update_user', $user);

            Log::info("User updated by {$actor->getAuthIdentifier()}: {$user->id}");

            return $user;
        } catch (\Exception $e) {
            Log::error("Failed to update user: {$e->getMessage()}");
            throw UserException::updateFailed($e->getMessage());
        }
    }

    /**
     * Get a user by ID
     *
     * @param  string  $id  The user ID
     * @return User The user
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getById(string $id): User
    {
        $user = User::with(['roles', 'memberProfile.county'])->findOrFail($id);

        // For non-staff members, load the full activity history
        if (! $user->isStaffMember()) {
            $user->load([
                'subscriptions',
                'donations',
                'eventOrders.event',
                'labBookings',
                'forumThreads',
                'forumPosts.thread',
            ]);
        }

        return $user;
    }

    /**
     * Delete a user (soft delete)
     *
     * @param  Authenticatable  $actor  The user performing the action
     * @param  User  $user  The user to delete
     * @return bool Success status
     *
     * @throws UserException
     */
    public function delete(Authenticatable $actor, User $user): bool
    {
        try {
            $user->delete();

            // Log audit trail
            $this->logAudit($actor, 'delete_user', $user);

            Log::info("User deleted by {$actor->getAuthIdentifier()}: {$user->id}");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete user: {$e->getMessage()}");
            throw UserException::deletionFailed($e->getMessage());
        }
    }

    /**
     * Restore a soft-deleted user
     *
     * @param  Authenticatable  $actor  The user performing the action
     * @param  User  $user  The user to restore
     * @return User The restored user
     *
     * @throws UserException
     */
    public function restore(Authenticatable $actor, User $user): User
    {
        try {
            $user->restore();

            // Log audit trail
            $this->logAudit($actor, 'restore_user', $user);

            Log::info("User restored by {$actor->getAuthIdentifier()}: {$user->id}");

            return $user;
        } catch (\Exception $e) {
            Log::error("Failed to restore user: {$e->getMessage()}");
            throw UserException::restorationFailed($e->getMessage());
        }
    }

    /**
     * Delete a user account (self-service)
     *
     * @param  User  $user  The user deleting their account
     * @param  string  $password  Their password for verification
     * @param  string|null  $reason  Optional reason for deletion
     * @return bool Success status
     *
     * @throws UserException
     */
    public function deleteSelf(User $user, string $password, ?string $reason = null): bool
    {
        try {
            // Verify password
            if (! Hash::check($password, $user->password)) {
                throw UserException::invalidPassword();
            }

            // Delete the user account
            $user->delete();

            // Log audit trail
            $this->logAudit($user, 'self_delete_user', $user);

            Log::info("User self-deleted: {$user->id}".($reason ? " - Reason: {$reason}" : ''));

            return true;
        } catch (UserException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Failed to delete user account: {$e->getMessage()}");
            throw UserException::deletionFailed($e->getMessage());
        }
    }

    /**
     * List users with filtering and pagination
     *
     * @param  array  $filters  Filters (search, role, status, etc.)
     * @param  int  $perPage  Results per page
     * @return LengthAwarePaginator Paginated users
     */
    public function listUsers(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = User::query()->with(['memberProfile.county', 'roles']);

        // Include trashed if requested
        if (! empty($filters['include_deleted'])) {
            $query->withTrashed();
        }

        // Search by username or email
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if (! empty($filters['role'])) {
            $query->role($filters['role']);
        }

        // Filter by verified status
        if (isset($filters['verified'])) {
            if ($filters['verified']) {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Force delete a user (permanent deletion)
     *
     * @param  Authenticatable  $actor  The user performing the action
     * @param  User  $user  The user to permanently delete
     * @return bool Success status
     *
     * @throws UserException
     */
    public function forceDelete(Authenticatable $actor, User $user): bool
    {
        try {
            // Log audit trail before deletion
            $this->logAudit($actor, 'force_delete_user', $user);

            // Delete profile picture if exists
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            // Permanently delete
            $user->forceDelete();

            Log::info("User permanently deleted by {$actor->getAuthIdentifier()}: {$user->id}");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to force delete user: {$e->getMessage()}");
            throw UserException::deletionFailed($e->getMessage());
        }
    }

    /**
     * Get user's audit log
     *
     * @param  User  $user  The user
     * @param  int  $limit  Maximum records
     * @return \Illuminate\Database\Eloquent\Collection Audit logs
     */
    public function getAuditLog(User $user, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere(function ($q) use ($user) {
                    $q->where('model_type', User::class)
                        ->where('model_id', $user->id);
                });
        })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Export user data for GDPR compliance
     *
     * @param  User  $user  The user whose data to export
     * @return array Exported data
     */
    public function exportData(User $user): array
    {
        $user->load([
            'memberProfile',
            'subscriptions',
            'donations',
            'eventOrders',
            'forumThreads',
            'forumPosts',
            'labBookings',
        ]);

        return [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'profile' => $user->memberProfile ? $user->memberProfile->toArray() : null,
            'subscriptions' => $user->subscriptions->toArray(),
            'donations' => $user->donations->toArray(),
            'event_orders' => $user->eventOrders->toArray(),
            'forum_threads' => $user->forumThreads->toArray(),
            'forum_posts' => $user->forumPosts->toArray(),
            'lab_bookings' => $user->labBookings->toArray(),
            'audit_logs' => $this->getAuditLog($user, 100)->toArray(),
            'exported_at' => now()->toISOString(),
        ];
    }

    /**
     * Upload user profile picture
     *
     * @param  User  $user  The user
     * @param  UploadedFile  $file  The image file
     * @return string The new profile picture URL
     *
     * @throws UserException
     */
    public function uploadProfilePicture(User $user, UploadedFile $file): string
    {
        try {
            // Validate through MediaService
            $this->mediaService->validateFile($file, $user);

            // Upload via MediaService (into public profile-pictures folder)
            $media = $this->mediaService->uploadMedia($user, $file, [
                'root_type' => 'public',
                'path' => ['profile-pictures'],
                'visibility' => 'public', // Profile pictures are usually public
            ]);

            // Update user with virtual path for backward compatibility or future use
            $user->update(['profile_picture_path' => $media->file_path]);

            Log::info("Profile picture uploaded via MediaService for user {$user->id}");

            return $media->getMediaUrl();
        } catch (\Exception $e) {
            Log::error("Failed to upload profile picture: {$e->getMessage()}");
            throw UserException::updateFailed($e->getMessage());
        }
    }

    /**
     * List all member profiles with filtering
     *
     * @param  array  $filters  Filters (county_id, membership_type, search, page)
     * @return array Paginated member profiles
     */
    public function listMemberProfiles(array $filters = []): array
    {
        $user = auth()->user();

        // Check authorization - only admins can list all profiles
        if (! $user || ! $user->hasAnyRole(['super_admin', 'admin'])) {
            throw new UserException('Unauthorized to view all profiles', 403);
        }

        $query = MemberProfile::with(['user', 'county']);

        if (! empty($filters['county_id'])) {
            $query->where('county_id', $filters['county_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = 20;
        $page = $filters['page'] ?? 1;

        return $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page)->toArray();
    }

    /**
     * Get current authenticated user's profile
     *
     * @return array User profile data
     */
    public function getCurrentUserProfile(): \App\Models\MemberProfile
    {
        $user = auth()->user();

        if (! $user) {
            throw new UserException('User not authenticated', 401);
        }

        $profile = \App\Models\MemberProfile::with(['user.subscriptions.plan', 'county', 'subscriptionPlan'])
            ->where('user_id', $user->id)
            ->first();

        if (! $profile) {
            throw new UserException('Profile not found', 404);
        }

        return $profile;
    }

    /**
     * Create or update member profile for authenticated user
     *
     * @param  CreateMemberProfileDTO  $dto  Profile data
     * @return MemberProfile Created or updated profile
     */
    public function createOrUpdateMemberProfile(CreateMemberProfileDTO $dto): MemberProfile
    {
        $user = auth()->user();

        if (! $user) {
            throw new UserException('User not authenticated', 401);
        }

        $data = $dto->toArray();

        // Parse first/last name from user's name if not provided
        if (empty($data['first_name']) && empty($data['last_name']) && $user->name) {
            $nameParts = explode(' ', $user->name, 2);
            $data['first_name'] = $nameParts[0] ?? '';
            $data['last_name'] = $nameParts[1] ?? '';
        }

        $profile = MemberProfile::updateOrCreate(
            ['user_id' => $user->id],
            array_merge($data, ['user_id' => $user->id])
        );

        return $profile->load(['user', 'county']);
    }

    /**
     * Delete a member profile
     *
     * @param  string  $id  Profile ID
     * @return bool Success status
     */
    public function deleteMemberProfile(string $id): bool
    {
        $user = auth()->user();

        // Check authorization - only admins can delete profiles
        if (! $user || ! $user->hasAnyRole(['super_admin', 'admin'])) {
            throw new UserException('Unauthorized to delete profile', 403);
        }

        $profile = MemberProfile::findOrFail($id);

        // Prevent admin from deleting their own profile
        if ($profile->user_id == $user->id) {
            throw new UserException('Cannot delete your own profile', 403);
        }

        $profile->delete();

        return true;
    }

    /**
     * Get a specific member profile
     *
     * @param  string|null  $id  Profile ID (null for current user)
     * @return array Profile data
     */
    public function getMemberProfile(?string $id = null): array
    {
        $user = auth()->user();

        if (! $user) {
            throw new UserException('User not authenticated', 401);
        }

        $profile = MemberProfile::with(['user', 'county'])->findOrFail($id);

        // Check authorization - users can view their own profile, admins can view any
        if ($profile->user_id !== $user->id && ! $user->hasAnyRole(['super_admin', 'admin'])) {
            throw new UserException('Unauthorized to view this profile', 403);
        }

        return $profile->toArray();
    }

    /**
     * Update a member profile
     *
     * @param  string  $id  Profile ID
     * @param  UpdateMemberProfileDTO  $dto  Profile data to update
     * @return MemberProfile Updated profile
     */
    public function updateMemberProfile(string $id, UpdateMemberProfileDTO $dto): MemberProfile
    {
        $user = auth()->user();

        if (! $user) {
            throw new UserException('User not authenticated', 401);
        }

        $profile = MemberProfile::findOrFail($id);

        // Check authorization - users can update their own profile, admins can update any
        if ($profile->user_id !== $user->id && ! $user->hasAnyRole(['super_admin', 'admin'])) {
            throw new UserException('Unauthorized to update this profile', 403);
        }

        $data = $dto->toArray();

        if (! empty($data)) {
            $profile->update($data);
        }

        return $profile->fresh(['user', 'county']);
    }

    /**
     * List all counties
     *
     * @return array List of counties
     */
    public function listCounties(): array
    {
        return County::orderBy('name')->get(['id', 'name'])->toArray();
    }

    /**
     * Log audit trail
     *
     * @param  User|Authenticatable  $actor  The user performing the action
     * @param  string  $action  The action performed
     * @param  User  $user  The affected user
     */
    private function logAudit(User|Authenticatable $actor, string $action, User $user): void
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
