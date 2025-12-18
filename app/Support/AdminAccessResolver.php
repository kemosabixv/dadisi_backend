<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use App\Models\User;

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
     * - Has any of the privileged roles (supports common variants)
     * - OR member profile `is_staff` is truthy
     *
     * @param  \App\Models\User|int|null  $user
     * @return bool
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

            // Common privileged role names used across the app. Include multiple variants to be defensive.
            $roles = [
                'super_admin', 'super admin', 'super-admin',
                'admin',
                'finance', 'finance_admin',
                'events_manager',
                'content_editor', 'editor',
            ];

            // Spatie's hasAnyRole will handle arrays and return false if roles are absent.
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles)) {
                return true;
            }

            // Fallback: member profile flag
            if (isset($user->memberProfile) && !empty($user->memberProfile->is_staff)) {
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning('AdminAccessResolver error: '.$e->getMessage());
            return false;
        }
    }
}
