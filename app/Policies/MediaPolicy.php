<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    /**
     * Determine if user can view any media (admin listing)
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage_media');
    }

    /**
     * Determine if user can view a specific media file
     */
    public function view(User $user, Media $media): bool
    {
        return $media->isAccessibleTo($user->id);
    }

    /**
     * Determine if user can create (upload) media
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('upload_post_media');
    }

    /**
     * Determine if user can update media metadata
     */
    public function update(User $user, Media $media): bool
    {
        // Only owner can update their own media
        return $user->id === $media->user_id;
    }

    /**
     * Determine if user can delete media
     */
    public function delete(User $user, Media $media): bool
    {
        // Only owner can delete their own media (soft delete)
        return $user->id === $media->user_id;
    }

    /**
     * Determine if user can permanently delete media
     */
    public function forceDelete(User $user, Media $media): bool
    {
        // Only owner can permanently delete their own media
        return $user->id === $media->user_id;
    }

    /**
     * Determine if user can restore soft-deleted media
     */
    public function restore(User $user, Media $media): bool
    {
        // Only owner can restore
        return $user->id === $media->user_id;
    }
}
