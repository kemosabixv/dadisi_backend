<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminMenuController extends Controller
{
    /**
     * Get authorized admin menu items for current user
     *
     * @group Admin Menu
     * @authenticated
     * @description Returns only menu items the current user is authorized to access based on their roles
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "key": "overview",
     *       "href": "/admin",
     *       "label": "Overview"
     *     },
     *     {
     *       "key": "analytics",
     *       "href": "/admin/analytics",
     *       "label": "Analytics"
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Define all menu items with their authorization checks
        $menuItems = [
            ['key' => 'overview', 'href' => '/admin', 'label' => 'Overview'],
            ['key' => 'analytics', 'href' => '/admin/analytics', 'label' => 'Analytics'],
            ['key' => 'user_management', 'href' => '/admin/users', 'label' => 'User Management'],
            ['key' => 'exchange_rates', 'href' => '/admin/exchange-rates', 'label' => 'Exchange Rates'],
            ['key' => 'roles_permissions', 'href' => '/admin/roles', 'label' => 'Roles & Permissions'],
            ['key' => 'data_retention', 'href' => '/admin/retention-settings', 'label' => 'Data Retention'],
            ['key' => 'audit_logs', 'href' => '/admin/audit-logs', 'label' => 'Audit Logs'],
            ['key' => 'blog', 'href' => '/admin/blog', 'label' => 'Blog Management'],
            ['key' => 'donations', 'href' => '/admin/donations', 'label' => 'Donations Management'],
            ['key' => 'events', 'href' => '/admin/events', 'label' => 'Event Management'],
            ['key' => 'system_settings', 'href' => '/admin/system-settings', 'label' => 'System Management'],
        ];

        // Filter based on user permissions via backend Policies
        $authorizedItems = array_filter($menuItems, function ($item) use ($user) {
            // Super admin sees all menu items
            if ($user->hasRole('super_admin')) {
                return true;
            }

            // Admin role sees everything except data retention and system settings
            if ($user->hasRole('admin')) {
                return !in_array($item['key'], ['data_retention', 'system_settings']);
            }

            // Finance role sees overview, analytics, donations, audit logs, and exchange rates
            if ($user->hasRole('finance')) {
                return in_array($item['key'], ['overview', 'analytics', 'donations', 'audit_logs', 'exchange_rates']);
            }

            // Events manager sees overview, events, analytics
            if ($user->hasRole('events_manager')) {
                return in_array($item['key'], ['overview', 'events', 'analytics']);
            }

            // Content editor sees overview, blog
            if ($user->hasRole('content_editor')) {
                return in_array($item['key'], ['overview', 'blog']);
            }

            // Regular members and other roles don't see admin menu
            return false;
        });

        return response()->json([
            'success' => true,
            'data' => array_values($authorizedItems),
        ]);
    }
}
