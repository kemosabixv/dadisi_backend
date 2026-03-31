<?php

namespace App\Services\Contracts;

use App\DTOs\CreateGroupDTO;
use App\DTOs\UpdateGroupDTO;
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
     * @param int|null $userId Optional user ID for membership check
     * @return LengthAwarePaginator
     */
    public function listGroups(array $filters = [], int $perPage = 20, ?int $userId = null): LengthAwarePaginator;

    /**
     * Create a new group
     *
     * @param CreateGroupDTO $dto
     * @return Group
     */
    public function createGroup(CreateGroupDTO $dto): Group;

    /**
     * Update an existing group
     *
     * @param Group $group
     * @param UpdateGroupDTO $dto
     * @return Group
     */
    public function updateGroup(Group $group, UpdateGroupDTO $dto): Group;

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
     * @return string The status of the join request (active or pending)
     */
    public function joinGroup(Group $group, Authenticatable $user): string;

    /**
     * Update member status (approve/reject)
     *
     * @param Group $group
     * @param int $userId
     * @param string $status
     * @return bool
     */
    public function updateMemberStatus(Group $group, int $userId, string $status): bool;

    /**
     * Leave a group
     *
     * @param Group $group
     * @param Authenticatable $user
     * @return bool
     */
    public function leaveGroup(Group $group, Authenticatable $user): bool;
}
