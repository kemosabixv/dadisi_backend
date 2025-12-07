<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PlanPolicy
{
    /**
     * Determine whether the user can view any subscription plans.
     * Used for the index endpoint - listing plans for subscription selection.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_plans');
    }

    /**
     * Determine whether the user can view a specific plan.
     * Used for the show endpoint - detailed plan information.
     */
    public function view(User $user, Plan $plan): bool
    {
        return $user->hasPermissionTo('view_plans');
    }

    /**
     * Determine whether the user can create new subscription plans.
     * Used for the store endpoint - admin plan creation.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_plans');
    }

    /**
     * Determine whether the user can update an existing plan.
     * Used for the update endpoint - admin plan modifications.
     */
    public function update(User $user, Plan $plan): bool
    {
        return $user->hasPermissionTo('manage_plans');
    }

    /**
     * Determine whether the user can delete a subscription plan.
     * Used for the destroy endpoint - admin plan removal.
     */
    public function delete(User $user, Plan $plan): bool
    {
        return $user->hasPermissionTo('manage_plans');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Plan $plan): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Plan $plan): bool
    {
        return false;
    }
}
