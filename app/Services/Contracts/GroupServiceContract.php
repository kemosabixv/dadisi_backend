<?php

namespace App\Services\Contracts;

use App\Models\Group;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * GroupServiceContract
 *
 * Defines contract for community group management.
 */
interface GroupServiceContract
{
    /**
     * List groups with filtering
     *
     * @param array $filters Filters (search, active)
     * @param int $perPage Results per page
     * @return LengthAwarePaginator
     */
    public function listGroups(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Create a new group
     *
     * @param array $data
     * @return Group
     */
    public function createGroup(array $data): Group;

    /**
     * Update an existing group
     *
     * @param Group $group
     * @param array $data
     * @return Group
     */
    public function updateGroup(Group $group, array $data): Group;

    /**
     * Delete a group
     *
     * @param Group $group
     * @return bool
     */
    public function deleteGroup(Group $group): bool;

    /**
     * List group members
     *
     * @param Group $group
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function listMembers(Group $group, int $perPage = 20): LengthAwarePaginator;

    /**
     * Remove a member from a group
     *
     * @param Group $group
     * @param int $userId
     * @return bool
     */
    public function removeMember(Group $group, int $userId): bool;

    /**
     * Join a group
     *
     * @param Group $group
     * @param Authenticatable $user
     * @return bool
     */
    public function joinGroup(Group $group, Authenticatable $user): bool;

    /**
     * Leave a group
     *
     * @param Group $group
     * @param Authenticatable $user
     * @return bool
     */
    public function leaveGroup(Group $group, Authenticatable $user): bool;
}
