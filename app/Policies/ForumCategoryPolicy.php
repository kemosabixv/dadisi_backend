<?php

namespace App\Policies;

use App\Models\ForumCategory;
use App\Models\User;

class ForumCategoryPolicy
{
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
        return $category->is_active || ($user && $user->hasAnyRole(['admin', 'super_admin', 'moderator']));
    }

    /**
     * Determine if the user can create categories.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    /**
     * Determine if the user can update the category.
     */
    public function update(User $user, ForumCategory $category): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    /**
     * Determine if the user can delete the category.
     */
    public function delete(User $user, ForumCategory $category): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }
}
