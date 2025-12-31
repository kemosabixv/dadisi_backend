<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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
     * 
     * Any authenticated user can create tags
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if user can update a tag
     * 
     * Staff: Can update any tag
     * Users: Can only update their own tags
     */
    public function update(User $user, Tag $tag): bool
    {
        // Staff can update any tag
        if ($user->isStaffMember() && $user->hasPermissionTo('edit_posts')) {
            return true;
        }

        // Users can update only their own tags
        return $user->id === $tag->created_by;
    }

    /**
     * Determine if user can delete a tag
     * 
     * Only staff members can delete tags (with confirmation)
     */
    public function delete(User $user, Tag $tag): Response
    {
        if (!$user->isStaffMember()) {
            return Response::deny('Only staff members can delete tags.');
        }

        if (!$user->hasPermissionTo('delete_posts')) {
            return Response::deny('You do not have permission to delete tags.');
        }

        return Response::allow();
    }

    /**
     * Get information about posts that will be affected by tag deletion
     * Used for deletion confirmation before cascade delete
     */
    public function getAffectedPosts(User $user, Tag $tag): array
    {
        if (!$this->delete($user, $tag)->allowed()) {
            return [];
        }

        $posts = $tag->posts()
            ->select('id', 'title', 'slug', 'user_id', 'status')
            ->with('author:id,username')
            ->get();

        return [
            'count' => $posts->count(),
            'posts' => $posts->toArray(),
        ];
    }
}
