<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    /**
     * Determine if user can view any tag
     */
    public function viewAny(User $user): bool
    {
        return true; // Public, anyone can view
    }

    /**
     * Determine if user can view a specific tag
     */
    public function view(User $user, Tag $tag): bool
    {
        return true; // Public
    }

    /**
     * Determine if user can create tags
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_post_tags');
    }

    /**
     * Determine if user can update tags
     */
    public function update(User $user, Tag $tag): bool
    {
        return $user->hasPermissionTo('manage_post_tags');
    }

    /**
     * Determine if user can delete tags
     */
    public function delete(User $user, Tag $tag): bool
    {
        return $user->hasPermissionTo('manage_post_tags');
    }
}
