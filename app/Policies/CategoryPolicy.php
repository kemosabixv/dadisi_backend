<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine if user can view any category
     */
    public function viewAny(User $user): bool
    {
        return true; // Public, anyone can view
    }

    /**
     * Determine if user can view a specific category
     */
    public function view(User $user, Category $category): bool
    {
        return true; // Public
    }

    /**
     * Determine if user can create categories
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_post_categories');
    }

    /**
     * Determine if user can update categories
     */
    public function update(User $user, Category $category): bool
    {
        // Admin roles have full access
        if ($user->hasAnyRole(['super_admin', 'admin', 'content_editor'])) {
            return true;
        }

        // Creator can update if they have the permission
        if ($user->id === $category->created_by && $user->hasPermissionTo('manage_post_categories')) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can delete categories
     */
    public function delete(User $user, Category $category): bool
    {
        // Admin roles have full access
        if ($user->hasAnyRole(['super_admin', 'admin', 'content_editor'])) {
            return true;
        }

        // Creator can delete if they have the permission
        if ($user->id === $category->created_by && $user->hasPermissionTo('manage_post_categories')) {
            return true;
        }

        return false;
    }
}
