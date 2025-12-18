<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models (list users).
     * Only admins can view user lists
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can view a specific user.
     * Admins can view any user, users can view themselves
     */
    public function view(User $user, User $model): bool
    {
        return $user->hasRole(['super_admin', 'admin']) || $user->id === $model->id;
    }

    /**
     * Determine whether the user can create users.
     * User creation is handled through registration, not admin creation
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('invite_users');
    }

    /**
     * Determine whether the user can update a user.
     * Admins can update any user
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can delete a user.
     * Admins can delete any user, users can delete themselves
     */
    public function delete(User $user, User $model): bool
    {
        return $user->hasRole(['super_admin', 'admin']) || $user->id === $model->id;
    }

    /**
     * Determine whether the user can restore deleted users.
     * Only super admins and admins can restore users
     */
    public function restore(User $user, User $model): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can permanently delete users.
     * Only super admins can force delete (after retention period)
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can view deleted users.
     * Only admins can see soft-deleted users
     */
    public function viewDeleted(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can view audit logs.
     * Only admins can view audit trails
     */
    public function viewAuditLogs(User $user): bool
    {
        return $user->hasPermissionTo('view_audit_logs');
    }

    /**
     * Determine whether the user can export user data.
     * Users can export their own data, admins can export any user's data
     */
    public function exportData(User $user, User $model): bool
    {
        return $user->hasRole(['super_admin', 'admin']) || $user->id === $model->id;
    }

    /**
     * Determine whether the user can assign roles.
     * Only super admins can assign roles
     */
    public function assignRoles(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can perform bulk operations.
     * Only admins can perform bulk operations
     */
    public function bulkOperations(User $user): bool
    {
        return $user->hasPermissionTo('bulk_user_operations');
    }

    /**
     * Determine whether the user can manage data retention settings.
     * Only super admins can modify retention settings
     */
    public function manageRetentionSettings(User $user): bool
    {
        return $user->hasRole('super_admin');
    }
}
