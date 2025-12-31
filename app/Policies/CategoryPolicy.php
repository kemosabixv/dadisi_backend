<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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
     * 
     * Any authenticated user can create categories
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if user can update a category
     * 
     * Staff: Can update any category
     * Users: Can only update their own categories
     */
    public function update(User $user, Category $category): bool
    {
        // Staff can update any category
        if ($user->isStaffMember() && $user->hasPermissionTo('edit_posts')) {
            return true;
        }

        // Users can update only their own categories
        return $user->id === $category->created_by;
    }

    /**
     * Determine if user can delete a category
     * 
     * Only staff members can delete categories (with confirmation)
     */
    public function delete(User $user, Category $category): Response
    {
        if (!$user->isStaffMember()) {
            return Response::deny('Only staff members can delete categories.');
        }

        if (!$user->hasPermissionTo('delete_posts')) {
            return Response::deny('You do not have permission to delete categories.');
        }

        return Response::allow();
    }

    /**
     * Get information about posts that will be affected by category deletion
     * Used for deletion confirmation before cascade delete
     */
    public function getAffectedPosts(User $user, Category $category): array
    {
        if (!$this->delete($user, $category)->allowed()) {
            return [];
        }

        $posts = $category->posts()
            ->select('id', 'title', 'slug', 'user_id', 'status')
            ->with('author:id,username')
            ->get();

        return [
            'count' => $posts->count(),
            'posts' => $posts->toArray(),
        ];
    }
}
