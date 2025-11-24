<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserDataRetentionSetting;
use Illuminate\Auth\Access\Response;

class UserDataRetentionSettingPolicy
{
    /**
     * Determine whether the user can view any retention settings.
     * Only super admins can manage retention settings
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can view a specific retention setting.
     * Only super admins can manage retention settings
     */
    public function view(User $user, UserDataRetentionSetting $retention): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can create retention settings.
     * Retention settings are managed through seeders, not API creation
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update retention settings.
     * Only super admins can modify retention settings
     */
    public function update(User $user, UserDataRetentionSetting $retention): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can delete retention settings.
     * Retention settings should not be deleted, only updated
     */
    public function delete(User $user, UserDataRetentionSetting $retention): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore retention settings.
     * Not applicable for retention settings
     */
    public function restore(User $user, UserDataRetentionSetting $retention): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete retention settings.
     * Not applicable for retention settings
     */
    public function forceDelete(User $user, UserDataRetentionSetting $retention): bool
    {
        return false;
    }
}
