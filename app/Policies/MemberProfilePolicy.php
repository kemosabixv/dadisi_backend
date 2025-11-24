<?php

namespace App\Policies;

use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MemberProfilePolicy
{
    /**
     * Determine whether the user can view any models (list all profiles)
     */
    public function viewAny(User $user): bool
    {
        // Only admins can view all profiles for reporting
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can view a specific profile
     */
    public function view(User $user, MemberProfile $memberProfile): bool
    {
        // Can view own profile or admin can view any profile
        return $memberProfile->user_id === $user->id ||
               $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can create models (not used, handled by registration)
     */
    public function create(User $user): bool
    {
        // Profile creation handled automatically during registration
        return false;
    }

    /**
     * Determine whether the user can update the model
     */
    public function update(User $user, MemberProfile $memberProfile): bool
    {
        // Can update own profile, or admin can manage all users
        return $memberProfile->user_id === $user->id || $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can delete the model
     */
    public function delete(User $user, MemberProfile $memberProfile): bool
    {
        // Deletion not allowed - profiles are tied to users and deleted via user deletion
        return false;
    }

    /**
     * Determine whether the user can restore the model (not implemented)
     */
    public function restore(User $user, MemberProfile $memberProfile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model (not implemented)
     */
    public function forceDelete(User $user, MemberProfile $memberProfile): bool
    {
        return false;
    }
}
