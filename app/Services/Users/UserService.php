<?php

namespace App\Services\Users;

use App\DTOs\CreateUserDTO;
use App\DTOs\UpdateUserDTO;
use App\Exceptions\UserException;
use App\Models\AuditLog;
use App\Models\County;
use App\Models\MemberProfile;
use App\Models\User;
use App\Services\Contracts\UserServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * User Service
 *
 * Handles user management operations including CRUD operations,
 * profile management, and account lifecycle.
 *
 * @package App\Services\Users
 */
class UserService implements UserServiceContract
{
    /**
     * Create a new user
     *
     * @param Authenticatable $actor The user performing the action
     * @param CreateUserDTO $dto User creation data
     * @return User The created user
     *
     * @throws UserException
     */
    public function create(Authenticatable $actor, CreateUserDTO $dto): User
    {
        try {
            $user = User::create($dto->toArray());

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
     * @param Authenticatable $actor The user performing the action
     * @param User $user The user to update
     * @param UpdateUserDTO $dto Updated user data
     * @return User The updated user
     *
     * @throws UserException
     */
    public function update(Authenticatable $actor, User $user, UpdateUserDTO $dto): User
    {
        try {
            $updateData = $dto->toArray();

            if (!empty($updateData)) {
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
     * @param string $id The user ID
     * @return User The user
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getById(string $id): User
    {
        return User::findOrFail($id);
    }

    /**
     * Delete a user (soft delete)
     *
     * @param Authenticatable $actor The user performing the action
     * @param User $user The user to delete
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
     * @param Authenticatable $actor The user performing the action
     * @param User $user The user to restore
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
     * @param User $user The user deleting their account
     * @param string $password Their password for verification
     * @param string|null $reason Optional reason for deletion
     * @return bool Success status
     *
     * @throws UserException
     */
    public function deleteSelf(User $user, string $password, ?string $reason = null): bool
    {
        try {
            // Verify password
            if (!Hash::check($password, $user->password)) {
                throw UserException::invalidPassword();
            }

            // Delete the user account
            $user->delete();

            // Log audit trail
            $this->logAudit($user, 'self_delete_user', $user);

            Log::info("User self-deleted: {$user->id}" . ($reason ? " - Reason: {$reason}" : ''));

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
     * @param array $filters Filters (search, role, status, etc.)
     * @param int $perPage Results per page
     * @return LengthAwarePaginator Paginated users
     */
    public function listUsers(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = User::query()->with(['memberProfile', 'roles']);

        // Include trashed if requested
        if (!empty($filters['include_deleted'])) {
            $query->withTrashed();
        }

        // Search by username or email
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if (!empty($filters['role'])) {
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
     * @param Authenticatable $actor The user performing the action
     * @param User $user The user to permanently delete
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
     * @param User $user The user
     * @param int $limit Maximum records
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
     * @param User $user The user whose data to export
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
     * @param User $user The user
     * @param UploadedFile $file The image file
     * @return string The new profile picture URL
     *
     * @throws UserException
     */
    public function uploadProfilePicture(User $user, UploadedFile $file): string
    {
        try {
            // Validate file type
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                throw UserException::updateFailed('Invalid file type. Only JPEG, PNG, WebP, and GIF are allowed.');
            }

            // Validate file size (max 5MB)
            if ($file->getSize() > 5 * 1024 * 1024) {
                throw UserException::updateFailed('File size exceeds 5MB limit.');
            }

            // Delete old profile picture if exists
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            // Store new profile picture
            $directory = 'profile-pictures';
            $filename = Str::slug($user->username) . '-' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $path = Storage::disk('public')->putFileAs($directory, $file, $filename);

            // Update user
            $user->update(['profile_picture' => $path]);

            Log::info("Profile picture uploaded for user {$user->id}");

            return url('/storage/' . $path);
        } catch (UserException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Failed to upload profile picture: {$e->getMessage()}");
            throw UserException::updateFailed('Failed to upload profile picture');
        }
    }

    /**
     * List all member profiles with filtering
     *
     * @param array $filters Filters (county_id, membership_type, search, page)
     * @return array Paginated member profiles
     */
    public function listMemberProfiles(array $filters = []): array
    {
        $user = auth()->user();
        
        // Check authorization - only admins can list all profiles
        if (!$user || !$user->hasAnyRole(['super_admin', 'admin'])) {
            throw new UserException('Unauthorized to view all profiles', 403);
        }

        $query = MemberProfile::with(['user', 'county']);

        if (!empty($filters['county_id'])) {
            $query->where('county_id', $filters['county_id']);
        }

        if (!empty($filters['search'])) {
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
    public function getCurrentUserProfile(): array
    {
        $user = auth()->user();
        
        if (!$user) {
            throw new UserException('User not authenticated', 401);
        }

        $profile = MemberProfile::with(['user', 'county'])
            ->where('user_id', $user->id)
            ->first();

        if (!$profile) {
            throw new UserException('Profile not found', 404);
        }

        return $profile->toArray();
    }

    /**
     * Create or update member profile for authenticated user
     *
     * @param array $data Profile data
     * @return array Created or updated profile
     */
    public function createOrUpdateMemberProfile(array $data): array
    {
        $user = auth()->user();
        
        if (!$user) {
            throw new UserException('User not authenticated', 401);
        }

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

        $profile->load(['user', 'county']);

        return $profile->toArray();
    }

    /**
     * Delete a member profile
     *
     * @param string $id Profile ID
     * @return bool Success status
     */
    public function deleteMemberProfile(string $id): bool
    {
        $user = auth()->user();
        
        // Check authorization - only admins can delete profiles
        if (!$user || !$user->hasAnyRole(['super_admin', 'admin'])) {
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
     * @param string|null $id Profile ID (null for current user)
     * @return array Profile data
     */
    public function getMemberProfile(?string $id = null): array
    {
        $user = auth()->user();
        
        if (!$user) {
            throw new UserException('User not authenticated', 401);
        }

        $profile = MemberProfile::with(['user', 'county'])->findOrFail($id);

        // Check authorization - users can view their own profile, admins can view any
        if ($profile->user_id !== $user->id && !$user->hasAnyRole(['super_admin', 'admin'])) {
            throw new UserException('Unauthorized to view this profile', 403);
        }

        return $profile->toArray();
    }

    /**
     * Update a member profile
     *
     * @param string $id Profile ID
     * @param array $data Profile data to update
     * @return array Updated profile
     */
    public function updateMemberProfile(string $id, array $data): array
    {
        $user = auth()->user();
        
        if (!$user) {
            throw new UserException('User not authenticated', 401);
        }

        $profile = MemberProfile::findOrFail($id);

        // Check authorization - users can update their own profile, admins can update any
        if ($profile->user_id !== $user->id && !$user->hasAnyRole(['super_admin', 'admin'])) {
            throw new UserException('Unauthorized to update this profile', 403);
        }

        $profile->update($data);
        $profile->load(['user', 'county']);

        return $profile->toArray();
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
     * @param User|Authenticatable $actor The user performing the action
     * @param string $action The action performed
     * @param User $user The affected user
     * @return void
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
