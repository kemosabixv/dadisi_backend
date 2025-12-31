<?php

namespace App\Policies;

use App\Models\ForumCategory;
use App\Models\User;

class ForumCategoryPolicy
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
     * Determine if the user can view any categories.
     */
    public function viewAny(?User $user): bool
    {
        return true; // Public
    }

    /**
     * Determine if the user can view the category.
     */
    public function view(?User $user, ForumCategory $category): bool
    {
        return $category->is_active || ($user && $this->isStaff($user));
    }

    /**
     * Determine if the user can create categories.
     * 
     * Subscribers (authenticated users) can create categories.
     */
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    /**
     * Determine if the user can update the category.
     * 
     * Subscribers can update categories (like blog pattern).
     */
    public function update(User $user, ForumCategory $category): bool
    {
        return $user->hasVerifiedEmail();
    }

    /**
     * Determine if the user can delete the category.
     * 
     * Only staff can delete categories.
     */
    public function delete(User $user, ForumCategory $category): bool
    {
        return $this->isStaff($user);
    }
}
