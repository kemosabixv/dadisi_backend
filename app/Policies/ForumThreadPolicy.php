<?php

namespace App\Policies;

use App\Models\ForumThread;
use App\Models\User;

class ForumThreadPolicy
{
    /**
     * Check if user is staff (via roles or member profile).
     */
    private function isStaff(User $user): bool
    {
        // Check roles first
        if ($user->hasAnyRole(['admin', 'super_admin', 'moderator', 'forum_moderator'])) {
            return true;
        }

        // Check is_staff on member profile or specific permission
        if ($user->can('moderate_forum') || ($user->memberProfile && $user->memberProfile->is_staff)) {
            return true;
        }

        return false;
    }

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
        // Author or staff
        if ($this->isStaff($user)) {
            return true;
        }

        return $user->id === $thread->user_id;
    }

    /**
     * Determine if the user can delete the thread.
     */
    public function delete(User $user, ForumThread $thread): bool
    {
        // Author or staff
        if ($this->isStaff($user)) {
            return true;
        }

        return $user->id === $thread->user_id;
    }

    /**
     * Determine if the user can moderate (pin/lock) the thread.
     */
    public function moderate(User $user, ForumThread $thread): bool
    {
        return $this->isStaff($user);
    }
}
