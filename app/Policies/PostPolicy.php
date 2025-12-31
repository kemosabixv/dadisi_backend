<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PostPolicy
{
    /**
     * Determine if user can view any post (for admin listing)
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_posts') || $user->hasPermissionTo('view_all_posts');
    }

    /**
     * Determine if user can view a specific post
     */
    public function view(User $user, Post $post): bool
    {
        // Public posts can be viewed by anyone
        if ($post->status === 'published') {
            return true;
        }

        // Author or admin can view unpublished
        return $user->id === $post->user_id || $user->hasPermissionTo('view_all_posts');
    }

    /**
     * Determine if user can create posts
     * 
     * Staff: Role-based (must have create_posts permission)
     * Subscribed Users: Feature-gated (checked in request/service)
     */
    public function create(User $user): bool
    {
        // Staff users: role-based check
        if ($user->isStaffMember()) {
            return $user->hasPermissionTo('create_posts');
        }

        // Subscribed users: feature-gating is handled in StoreAuthorPostRequest
        // This policy method returns true; actual quota check happens in the request
        return true;
    }

    /**
     * Determine if user can update a post
     * 
     * Staff: Can edit any post
     * Subscribed Users: Can only edit their own posts
     */
    public function update(User $user, Post $post): bool
    {
        // Staff can edit any post
        if ($user->isStaffMember() && $user->hasPermissionTo('edit_posts')) {
            return true;
        }

        // Subscribed users can edit only their own posts
        if ($user->id === $post->user_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can delete a post
     * 
     * Staff: Can delete any post
     * Subscribed Users: Can only delete their own posts
     */
    public function delete(User $user, Post $post): bool
    {
        // Staff can delete any post
        if ($user->isStaffMember() && $user->hasPermissionTo('delete_posts')) {
            return true;
        }

        // Subscribed users can delete only their own posts
        if ($user->id === $post->user_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can restore a post
     */
    public function restore(User $user, Post $post): bool
    {
        return $user->hasPermissionTo('restore_posts');
    }

    /**
     * Determine if user can permanently delete a post
     */
    public function forceDelete(User $user, Post $post): bool
    {
        return $user->hasPermissionTo('force_delete_posts');
    }

    /**
     * Determine if user can publish a post
     */
    public function publish(User $user, Post $post): bool
    {
        return $user->hasPermissionTo('publish_posts');
    }

    /**
     * Determine if user can unpublish a post
     */
    public function unpublish(User $user, Post $post): bool
    {
        return $user->hasPermissionTo('publish_posts');
    }
}

