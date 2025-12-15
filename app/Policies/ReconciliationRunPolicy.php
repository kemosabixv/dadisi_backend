<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ReconciliationRun;

class ReconciliationRunPolicy
{
    /**
     * Determine if the user can view reconciliation runs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_reconciliation');
    }

    /**
     * Determine if the user can view a specific reconciliation run.
     */
    public function view(User $user, ReconciliationRun $run): bool
    {
        return $user->hasPermissionTo('view_reconciliation');
    }

    /**
     * Determine if the user can manage (create/trigger) reconciliation runs.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_reconciliation');
    }

    /**
     * Determine if the user can trigger reconciliation.
     */
    public function trigger(User $user): bool
    {
        return $user->hasPermissionTo('manage_reconciliation');
    }

    /**
     * Determine if the user can export a reconciliation run.
     */
    public function export(User $user, ReconciliationRun $run): bool
    {
        return $user->hasPermissionTo('view_reconciliation');
    }

    /**
     * Determine if the user can delete a reconciliation run.
     */
    public function delete(User $user, ReconciliationRun $run): bool
    {
        return $user->hasPermissionTo('manage_reconciliation');
    }
}
