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
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_posts');
    }

    /**
     * Determine if user can update a post
     */
    public function update(User $user, Post $post): bool
    {
        // Admin can edit any post
        if ($user->hasPermissionTo('edit_any_post')) {
            return true;
        }

        // User can edit their own if they have edit permission
        if ($user->id === $post->user_id && $user->hasPermissionTo('edit_posts')) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can delete a post
     */
    public function delete(User $user, Post $post): bool
    {
        // Admin can delete any post
        if ($user->hasPermissionTo('delete_any_post')) {
            return true;
        }

        // User can delete their own if they have delete permission
        if ($user->id === $post->user_id && $user->hasPermissionTo('delete_posts')) {
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
}
