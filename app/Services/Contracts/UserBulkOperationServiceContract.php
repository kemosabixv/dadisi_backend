<?php

namespace App\Services\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * User Bulk Operation Service Contract
 *
 * Defines the interface for bulk user operations including
 * bulk role assignments, deletions, restorations, and updates.
 *
 * @package App\Services\Contracts
 */
interface UserBulkOperationServiceContract
{
    /**
     * Bulk assign a role to multiple users
     *
     * @param Authenticatable $actor The user performing the action
     * @param array $userIds Array of user IDs (max 100)
     * @param string $role The role name to assign
     * @return int Number of users updated
     *
     * @throws \App\Exceptions\UserException
     */
    public function bulkAssignRole(Authenticatable $actor, array $userIds, string $role): int;

    /**
     * Bulk remove a role from multiple users
     *
     * @param Authenticatable $actor The user performing the action
     * @param array $userIds Array of user IDs (max 100)
     * @param string $role The role name to remove
     * @return int Number of users updated
     *
     * @throws \App\Exceptions\UserException
     */
    public function bulkRemoveRole(Authenticatable $actor, array $userIds, string $role): int;

    /**
     * Bulk delete users (soft delete)
     *
     * @param Authenticatable $actor The user performing the action
     * @param array $userIds Array of user IDs (max 50)
     * @return int Number of users deleted
     *
     * @throws \App\Exceptions\UserException
     */
    public function bulkDelete(Authenticatable $actor, array $userIds): int;

    /**
     * Bulk restore deleted users
     *
     * @param Authenticatable $actor The user performing the action
     * @param array $userIds Array of user IDs (max 50)
     * @return int Number of users restored
     *
     * @throws \App\Exceptions\UserException
     */
    public function bulkRestore(Authenticatable $actor, array $userIds): int;

    /**
     * Bulk update user data
     *
     * @param Authenticatable $actor The user performing the action
     * @param array $userIds Array of user IDs (max 50)
     * @param array $data Data to update (username, email, etc.)
     * @return int Number of users updated
     *
     * @throws \App\Exceptions\UserException
     */
    public function bulkUpdate(Authenticatable $actor, array $userIds, array $data): int;
}
