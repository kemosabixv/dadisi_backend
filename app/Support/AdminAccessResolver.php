<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Centralized admin access resolver.
 * Computes boolean capability `can_access_admin` used by UI and middleware.
 */
class AdminAccessResolver
{
    /**
     * Determine whether the given user can access admin features.
     *
     * Rules:
     * - Has the 'access_admin_panel' permission
     *
     * @param  \App\Models\User|int|null  $user
     */
    public static function canAccessAdmin($user): bool
    {
        if (! $user) {
            return false;
        }

        try {
            // Accept either a User model or an id
            if (is_int($user) || is_string($user)) {
                $user = User::find($user);
                if (! $user) {
                    return false;
                }
            }

            // check for capability-based permission
            if (method_exists($user, 'can')) {
                $hasPermission = $user->can('access_admin_panel');

                if ($hasPermission) {
                    return true;
                }
            }


            return false;
        } catch (\Throwable $e) {
            Log::warning('AdminAccessResolver error: '.$e->getMessage());

            return false;
        }
    }
}
