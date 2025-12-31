<?php

namespace App\Services\Contracts;

use App\DTOs\CreateUserDTO;
use App\DTOs\UpdateUserDTO;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * User Service Contract
 *
 * Defines the interface for user management operations including CRUD,
 * profile management, and account lifecycle operations.
 *
 * @package App\Services\Contracts
 */
interface UserServiceContract
{
    /**
     * Create a new user
     *
     * @param Authenticatable $actor The user performing the action
     * @param CreateUserDTO $dto User creation data
     * @return User The created user
     *
     * @throws \App\Exceptions\UserException
     */
    public function create(Authenticatable $actor, CreateUserDTO $dto): User;

    /**
     * Update an existing user
     *
     * @param Authenticatable $actor The user performing the action
     * @param User $user The user to update
     * @param UpdateUserDTO $dto Updated user data
     * @return User The updated user
     *
     * @throws \App\Exceptions\UserException
     */
    public function update(Authenticatable $actor, User $user, UpdateUserDTO $dto): User;

    /**
     * Get a user by ID
     *
     * @param string $id The user ID
     * @return User The user
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getById(string $id): User;

    /**
     * Delete a user (soft delete)
     *
     * @param Authenticatable $actor The user performing the action
     * @param User $user The user to delete
     * @return bool Success status
     *
     * @throws \App\Exceptions\UserException
     */
    public function delete(Authenticatable $actor, User $user): bool;

    /**
     * Restore a soft-deleted user
     *
     * @param Authenticatable $actor The user performing the action
     * @param User $user The user to restore
     * @return User The restored user
     *
     * @throws \App\Exceptions\UserException
     */
    public function restore(Authenticatable $actor, User $user): User;

    /**
     * Delete a user account (self-service)
     *
     * @param User $user The user deleting their account
     * @param string $password Their password for verification
     * @param string|null $reason Optional reason for deletion
     * @return bool Success status
     *
     * @throws \App\Exceptions\UserException
     */
    public function deleteSelf(User $user, string $password, ?string $reason = null): bool;

    /**
     * List users with filtering and pagination
     *
     * @param array $filters Filters (search, role, status, etc.)
     * @param int $perPage Results per page
     * @return LengthAwarePaginator Paginated users
     */
    public function listUsers(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Force delete a user (permanent deletion)
     *
     * @param Authenticatable $actor The user performing the action
     * @param User $user The user to permanently delete
     * @return bool Success status
     *
     * @throws \App\Exceptions\UserException
     */
    public function forceDelete(Authenticatable $actor, User $user): bool;

    /**
     * Get user's audit log
     *
     * @param User $user The user
     * @param int $limit Maximum records
     * @return \Illuminate\Database\Eloquent\Collection Audit logs
     */
    public function getAuditLog(User $user, int $limit = 50): \Illuminate\Database\Eloquent\Collection;

    /**
     * Export user data for GDPR compliance
     *
     * @param User $user The user whose data to export
     * @return array Exported data
     */
    public function exportData(User $user): array;

    /**
     * Upload user profile picture
     *
     * @param UploadedFile $file The image file
     * @return string The path/URL of the uploaded profile picture
     *
     * @throws \App\Exceptions\UserException
     */
    public function uploadProfilePicture(\App\Models\User $user, \Illuminate\Http\UploadedFile $file): string;

    /**
     * List all member profiles with filtering
     *
     * @param array $filters Filters (county_id, membership_type, search, page)
     * @return array Paginated member profiles
     */
    public function listMemberProfiles(array $filters = []): array;

    /**
     * Get current authenticated user's profile
     *
     * @return array User profile data
     */
    public function getCurrentUserProfile(): array;

    /**
     * Create or update member profile for authenticated user
     *
     * @param array $data Profile data
     * @return array Created or updated profile
     */
    public function createOrUpdateMemberProfile(array $data): array;

    /**
     * Delete a member profile
     *
     * @param string $id Profile ID
     * @return bool Success status
     */
    public function deleteMemberProfile(string $id): bool;

    /**
     * Get a specific member profile
     *
     * @param string|null $id Profile ID (null for current user)
     * @return array Profile data
     */
    public function getMemberProfile(?string $id = null): array;

    /**
     * Update a member profile
     *
     * @param string $id Profile ID
     * @param array $data Profile data to update
     * @return array Updated profile
     */
    public function updateMemberProfile(string $id, array $data): array;

    /**
     * List all counties
     *
     * @return array List of counties
     */
    public function listCounties(): array;
}
