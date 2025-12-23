<?php

namespace App\Policies;

use App\Models\ForumTag;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ForumTagPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if admin has full access.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return null;
    }

    /**
     * Anyone can view tags.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Anyone can view a tag.
     */
    public function view(?User $user, ForumTag $tag): bool
    {
        return true;
    }

    /**
     * Only admins can create tags.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_forum_tags');
    }

    /**
     * Only admins can update tags.
     */
    public function update(User $user, ForumTag $tag): bool
    {
        return $user->hasPermissionTo('manage_forum_tags');
    }

    /**
     * Only admins can delete tags.
     */
    public function delete(User $user, ForumTag $tag): bool
    {
        return $user->hasPermissionTo('manage_forum_tags');
    }
}
