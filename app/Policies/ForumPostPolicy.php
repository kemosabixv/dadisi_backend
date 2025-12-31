<?php

namespace App\Policies;

use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\User;

class ForumPostPolicy
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
     * Determine if the user can create posts in a thread.
     */
    public function create(User $user, ForumThread $thread): bool
    {
        // Must be authenticated and thread not locked
        if (!$user->hasVerifiedEmail()) {
            return false;
        }
        return !$thread->is_locked;
    }

    /**
     * Determine if the user can update the post.
     */
    public function update(User $user, ForumPost $post): bool
    {
        // Author or staff
        if ($this->isStaff($user)) {
            return true;
        }
        return $user->id === $post->user_id;
    }

    /**
     * Determine if the user can delete the post.
     */
    public function delete(User $user, ForumPost $post): bool
    {
        // Staff only for deletion
        return $this->isStaff($user);
    }
}
