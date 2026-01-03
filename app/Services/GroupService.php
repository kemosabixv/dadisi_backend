<?php

namespace App\Services;

use App\Models\Group;
use App\Services\Contracts\GroupServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * GroupService
 *
 * Handles business logic for community groups.
 */
class GroupService implements GroupServiceContract
{
    /**
     * @inheritDoc
     */
    public function listGroups(array $filters = [], int $perPage = 20, ?int $userId = null): LengthAwarePaginator
    {
        $query = Group::with('county')
            ->withCount('members')
            ->withCount('forumThreads as thread_count');

        if ($userId) {
            $query->withExists(['members as is_member' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }]);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        if (isset($filters['active'])) {
            $query->where('is_active', (bool)$filters['active']);
        }

        // Handle sorting
        $sortBy = $filters['sort'] ?? 'name';
        $sortDir = $filters['order'] ?? 'asc';
        
        if ($sortBy === 'member_count') {
            $query->orderBy('members_count', $sortDir);
        } elseif ($sortBy === 'thread_count') {
            $query->orderBy('thread_count', $sortDir);
        } else {
            $query->orderBy('name', $sortDir);
        }

        return $query->paginate($perPage);
    }

    /**
     * @inheritDoc
     */
    public function createGroup(array $data): Group
    {
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return Group::create($data);
    }

    /**
     * @inheritDoc
     */
    public function updateGroup(Group $group, array $data): Group
    {
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $group->update($data);

        return $group;
    }

    /**
     * @inheritDoc
     */
    public function deleteGroup(Group $group): bool
    {
        return $group->delete();
    }

    /**
     * @inheritDoc
     */
    public function listMembers(Group $group, int $perPage = 20): LengthAwarePaginator
    {
        return $group->members()
            ->with('memberProfile:user_id,first_name,last_name')
            ->paginate($perPage);
    }

    /**
     * @inheritDoc
     */
    public function removeMember(Group $group, int $userId): bool
    {
        $group->members()->detach($userId);
        $group->updateMemberCount();

        return true;
    }

    /**
     * @inheritDoc
     */
    public function joinGroup(Group $group, Authenticatable $user): bool
    {
        if ($group->members()->where('user_id', $user->getAuthIdentifier())->exists()) {
            return true;
        }

        $group->members()->attach($user->getAuthIdentifier());
        $group->updateMemberCount();

        return true;
    }

    /**
     * @inheritDoc
     */
    public function leaveGroup(Group $group, Authenticatable $user): bool
    {
        $group->members()->detach($user->getAuthIdentifier());
        $group->updateMemberCount();

        return true;
    }
}
