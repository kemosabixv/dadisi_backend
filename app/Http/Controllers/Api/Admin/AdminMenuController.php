<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminMenuController extends Controller
{
    /**
     * Get Authorized Admin Menu
     *
     * Returns a dynamic list of menu items based on the authenticated user's permissions.
     * This endpoint is used by the frontend to render the admin sidebar/navigation.
     *
     * @group Admin
     * @middleware auth:sanctum,admin
     *
     * @response 200 [
     *   { "key": "users", "label": "User Management" },
     *   { "key": "finance", "label": "Finance" }
     * ]
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $menu = [];

        // Example menu items - adapt based on actual permissions system
        // These checks should match the Spatie permissions defined in the system
        
        if ($user->can('manage_users') || $user->hasRole('super_admin')) {
             $menu[] = ['key' => 'users', 'label' => 'Users', 'href' => '/admin/users'];
        }

        if ($user->can('view_finances') || $user->hasRole(['super_admin', 'finance'])) {
            $menu[] = ['key' => 'finance', 'label' => 'Finance', 'href' => '/admin/finance'];
        }

        if ($user->can('manage_events') || $user->hasRole(['super_admin', 'events_manager'])) {
            $menu[] = ['key' => 'events', 'label' => 'Events', 'href' => '/admin/events'];
        }

        if ($user->can('manage_content') || $user->hasRole(['super_admin', 'content_editor'])) {
            $menu[] = ['key' => 'blog', 'label' => 'Blog', 'href' => '/admin/blog'];
        }
        
        // Settings - typically super_admin only
        if ($user->hasRole('super_admin')) {
            $menu[] = ['key' => 'settings', 'label' => 'Settings', 'href' => '/admin/settings'];
        }

        // Just for fallback/testing if no specific roles, but is_staff is true, show dashboard
        $menu[] = ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/admin'];

        return response()->json($menu);
    }
}
