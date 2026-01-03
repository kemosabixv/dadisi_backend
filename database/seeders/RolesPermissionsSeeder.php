<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
            'delete_any_forum_post',
            'ban_forum_users',
            'mute_forum_users',
            'view_forum_reports',
            'manage_forum_categories',
            'manage_forum_tags',
            'send_messages',
            'view_messages',

            // County Management
            'manage_counties',

            // Lab Space Booking
            'manage_lab_spaces',
            'view_all_lab_bookings',
            'approve_lab_bookings',
            'manage_lab_maintenance',
            'view_lab_reports',
            'mark_lab_attendance',

            // Group Management
            'manage_groups',
            'manage_group_members',

            // Student Approvals
            'view_student_approvals',
            'approve_student_approvals',
            'reject_student_approvals',

            // Retention Settings
            'manage_retention_settings',
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
                'manage_counties', 'manage_forum_tags',
                // Lab Space Booking
                'manage_lab_spaces', 'view_all_lab_bookings', 'approve_lab_bookings',
                'manage_lab_maintenance', 'view_lab_reports', 'mark_lab_attendance',
                // Forum Admin
                'view_forum', 'create_threads', 'reply_threads', 'moderate_forum', 'lock_threads', 'pin_threads', 'delete_any_thread', 'delete_any_forum_post', 'manage_forum_categories', 'manage_forum_tags',
                // Student Approvals
                'view_student_approvals', 'approve_student_approvals', 'reject_student_approvals',
                // Retention Settings
                'manage_retention_settings',
            ],
            'finance' => [
                'view_donation_ledger', 'export_donations', 'reconcile_payments', 'view_reports',
                'view_exchange_rates', 'refresh_exchange_rates', 'view_plans',
                'view_reconciliation', 'manage_reconciliation',
                // Admin Panel Access
                'view_audit_logs', // Often needed for finance to check transaction logs
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
            'member' => [
                'manage_own_profile', 'rsvp_events', 'make_donations', 'view_plans',
                'view_forum', 'create_threads', 'reply_threads', 'send_messages', 'view_messages',
            ],
            'moderator' => [
                'view_forum', 'create_threads', 'reply_threads',
                'moderate_forum', 'lock_threads', 'pin_threads', 'delete_any_thread',
                'manage_groups', 'manage_group_members',
            ],
            'forum_moderator' => [
                'view_forum', 'create_threads', 'reply_threads', 'send_messages', 'view_messages',
                'moderate_forum', 'lock_threads', 'pin_threads', 'delete_any_thread', 'delete_any_forum_post',
                'manage_forum_categories', 'manage_forum_tags', 'manage_groups', 'manage_group_members',
            ],
            'lab_manager' => [
                // Lab space management role (not full admin)
                'view_all_lab_bookings', 'approve_lab_bookings',
                'manage_lab_maintenance', 'view_lab_reports', 'mark_lab_attendance',
            ],
        ];

        // Create roles and assign permissions for both guards
        $guards = ['web', 'api'];

        foreach ($guards as $guard) {
            foreach ($rolePermissions as $roleName => $rolePerms) {
                // Ensure permission exists for this guard
                foreach ($rolePerms as $permName) {
                    Permission::firstOrCreate(['name' => $permName, 'guard_name' => $guard]);
                }

                if ($roleName === 'super_admin') {
                    // Super admin gets all permissions for this guard
                    // Need to make sure they are created for this guard first
                    foreach ($permissions as $permission) {
                        Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guard]);
                    }
                }

                $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);

                if ($roleName === 'super_admin') {
                    $role->givePermissionTo(Permission::where('guard_name', $guard)->get());
                } else {
                    $role->givePermissionTo($rolePerms);
                }
            }
        }

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('Available roles: '.implode(', ', array_keys($rolePermissions)));
    }
}
