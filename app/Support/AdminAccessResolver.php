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
     * - Has any of the privileged roles (supports common variants)
     * - OR member profile `is_staff` is truthy
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

            // Common privileged role names used across the app. Include multiple variants to be defensive.
            $roles = [
                'super_admin', 'super admin', 'super-admin',
                'admin',
                'finance', 'finance_admin',
                'events_manager',
                'content_editor', 'editor',
                'moderator', 'forum_moderator',
                'lab_manager',
            ];

            // Spatie's hasAnyRole checks roles regardless of guard by default
            if (method_exists($user, 'hasAnyRole')) {
                // Check if user has any of the admin roles (Spatie checks all guards the model uses)
                $hasRole = $user->hasAnyRole($roles);

                Log::info('AdminAccessResolver check', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'has_role' => $hasRole,
                    'user_roles' => $user->getRoleNames()->toArray(),
                    'check_result' => $hasRole ? 'GRANTED' : 'DENIED',
                ]);

                if ($hasRole) {
                    return true;
                }
            }

            // Fallback: member profile flag
            if (isset($user->memberProfile) && ! empty($user->memberProfile->is_staff)) {
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning('AdminAccessResolver error: '.$e->getMessage());

            return false;
        }
    }
}
