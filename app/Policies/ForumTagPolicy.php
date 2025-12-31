<?php

namespace App\Policies;

use App\Models\ForumTag;
use App\Models\User;

class ForumTagPolicy
{
    /**
     * Check if user is staff (via roles or member profile).
     */
    private function isStaff(User $user): bool
    {
        if ($user->hasAnyRole(['admin', 'super_admin', 'moderator', 'forum_moderator'])) {
            return true;
        }
        
        if ($user->can('moderate_forum') || ($user->memberProfile && $user->memberProfile->is_staff)) {
            return true;
        }
        
        return false;
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
     * Subscribers (authenticated users) can create tags.
     */
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    /**
     * Subscribers can update tags.
     */
    public function update(User $user, ForumTag $tag): bool
    {
        return $user->hasVerifiedEmail();
    }

    /**
     * Only staff can delete tags.
     */
    public function delete(User $user, ForumTag $tag): bool
    {
        return $this->isStaff($user);
    }
}
