<?php

namespace App\Policies;

use App\Models\ForumThread;
use App\Models\User;

class ForumThreadPolicy
{
    /**
     * Determine if the user can view any threads.
     */
    public function viewAny(?User $user): bool
    {
        return true; // Public
    }

    /**
     * Determine if the user can view the thread.
     */
    public function view(?User $user, ForumThread $thread): bool
    {
        return true; // Public
    }

    /**
     * Determine if the user can create threads.
     */
    public function create(User $user): bool
    {
        // Must be authenticated member
        return $user->hasVerifiedEmail();
    }

    /**
     * Determine if the user can update the thread.
     */
    public function update(User $user, ForumThread $thread): bool
    {
        // Author or moderator/admin
        if ($user->hasAnyRole(['admin', 'super_admin', 'moderator'])) {
            return true;
        }
        return $user->id === $thread->user_id;
    }

    /**
     * Determine if the user can delete the thread.
     */
    public function delete(User $user, ForumThread $thread): bool
    {
        // Author or moderator/admin
        if ($user->hasAnyRole(['admin', 'super_admin', 'moderator'])) {
            return true;
        }
        return $user->id === $thread->user_id;
    }

    /**
     * Determine if the user can moderate (pin/lock) the thread.
     */
    public function moderate(User $user, ForumThread $thread): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin', 'moderator']);
    }
}
