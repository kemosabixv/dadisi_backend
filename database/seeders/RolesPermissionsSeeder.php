<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing permissions and roles
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User Management
            'manage_users',
            'view_all_users',
            'invite_users',
            'bulk_user_operations',
            'assign_roles',
            'view_audit_logs',

            // RBAC Management
            'manage_permissions',
            'manage_roles',

            // Events
            'create_events',
            'edit_events',
            'delete_events',
            'view_all_events',
            'manage_event_attendees',

            // Donations
            'view_donation_ledger',
            'export_donations',
            'reconcile_payments',

            // Content/Blog
            'create_posts',
            'edit_posts',
            'publish_posts',
            'delete_posts',
            'view_posts',
            'view_all_posts',
            'edit_any_post',
            'delete_any_post',

            // Category/Tag management for posts
            'manage_post_categories',
            'manage_post_tags',

            // Media uploads and management
            'upload_post_media',
            'manage_media',

            // System
            'view_reports',

            // Exchange Rate Management
            'manage_exchange_rates',
            'view_exchange_rates',
            'refresh_exchange_rates',
            'configure_exchange_rates',

            // Plan Management
            'view_plans',
            'manage_plans',

            // Phase 3: Reconciliation Management
            'view_reconciliation',
            'manage_reconciliation',

            // Member access
            'manage_own_profile',
            'rsvp_events',
            'make_donations',

            // Forum (Planned)
            'view_forum',
            'create_threads',
            'reply_threads',
            'moderate_forum',
            'lock_threads',
            'pin_threads',
            'delete_any_thread',
            'ban_forum_users',
            'mute_forum_users',
            'view_forum_reports',
            'manage_forum_categories',
            'send_messages',
            'view_messages',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Define role permissions mapping
        $rolePermissions = [
            'super_admin' => [
                // All permissions
            ],
            'admin' => [
                'manage_users', 'view_all_users', 'invite_users', 'bulk_user_operations', 'view_audit_logs', 'create_events', 'edit_events',
                'delete_events', 'view_all_events', 'manage_event_attendees',
                'view_donation_ledger', 'export_donations', 'create_posts',
                'edit_posts', 'publish_posts', 'delete_posts', 'view_posts', 'view_all_posts', 'view_reports',
                'manage_exchange_rates', 'view_exchange_rates',
                'refresh_exchange_rates', 'configure_exchange_rates',
                'view_plans', 'manage_plans',
                'view_reconciliation', 'manage_reconciliation',
                'manage_permissions', 'manage_roles',
            ],
            'finance' => [
                'view_donation_ledger', 'export_donations', 'reconcile_payments', 'view_reports',
                'view_exchange_rates', 'refresh_exchange_rates', 'view_plans',
                'view_reconciliation', 'manage_reconciliation',
            ],
            'events_manager' => [
                'create_events', 'edit_events', 'delete_events', 'view_all_events', 'manage_event_attendees', 'view_reports',
                'view_plans',
            ],
            'content_editor' => [
                'create_posts', 'edit_posts', 'publish_posts', 'delete_posts', 'view_posts',
                'view_plans',
            ],
            'editor' => [
                'create_posts', 'edit_posts', 'publish_posts', 'view_posts',
            ],
            'author' => [
                // Premium members that can create and publish their own posts
                'view_posts', 'create_posts', 'edit_posts', 'publish_posts', 'upload_post_media',
            ],
            'member' => [
                'manage_own_profile', 'rsvp_events', 'make_donations', 'view_plans',
                'view_forum', 'create_threads', 'reply_threads', 'send_messages', 'view_messages',
            ],
            'moderator' => [
                // Forum moderation role
                'view_forum', 'create_threads', 'reply_threads', 'send_messages', 'view_messages',
                'moderate_forum', 'lock_threads', 'pin_threads', 'delete_any_thread',
                'ban_forum_users', 'mute_forum_users', 'view_forum_reports',
            ],
        ];

        // Create roles and assign permissions
        foreach ($rolePermissions as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            if ($roleName === 'super_admin') {
                // Super admin gets all permissions
                $role->givePermissionTo(Permission::all());
            } else {
                // Assign specific permissions to role
                $role->givePermissionTo($rolePerms);
            }
        }

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('Available roles: ' . implode(', ', array_keys($rolePermissions)));
    }
}
