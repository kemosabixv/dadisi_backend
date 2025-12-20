<?php

namespace App\Policies;

use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\User;

class ForumPostPolicy
{
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
        // Author or moderator/admin
        if ($user->hasAnyRole(['admin', 'super_admin', 'moderator'])) {
            return true;
        }
        return $user->id === $post->user_id;
    }

    /**
     * Determine if the user can delete the post.
     */
    public function delete(User $user, ForumPost $post): bool
    {
        // Author or moderator/admin
        if ($user->hasAnyRole(['admin', 'super_admin', 'moderator'])) {
            return true;
        }
        return $user->id === $post->user_id;
    }
}
