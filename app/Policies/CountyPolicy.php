<?php

namespace App\Policies;

use App\Models\County;
use App\Models\User;

class CountyPolicy
{
    /**
     * Anyone can view counties (public).
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Anyone can view a single county (public).
     */
    public function view(?User $user, County $county): bool
    {
        return true;
    }

    /**
     * Only users with manage_counties permission can create.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_counties');
    }

    /**
     * Only users with manage_counties permission can update.
     */
    public function update(User $user, County $county): bool
    {
        return $user->hasPermissionTo('manage_counties');
    }

    /**
     * Only users with manage_counties permission can delete.
     */
    public function delete(User $user, County $county): bool
    {
        return $user->hasPermissionTo('manage_counties');
    }
}
