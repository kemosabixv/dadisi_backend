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
            'manage_system_settings',

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
            'edit_lab_space',
            'view_all_lab_bookings',
            'manage_lab_maintenance',
            'view_lab_reports',
            'mark_lab_attendance',
            'disable_lab_bookings',
            'enable_lab_bookings',
            'cancel_lab_booking',
            'initiate_lab_refund',

            // Group Management
            'manage_groups',
            'manage_group_members',

            // Student Approvals
            'view_student_approvals',
            'approve_student_approvals',
            'reject_student_approvals',

            // Retention Settings
            'manage_retention_settings',

            // Finance & Payments
            'view_finance_analytics',
            'manage_payments',
            'manage_refunds',

            // Subscription Management
            'view_subscriptions',
            'manage_subscriptions',

            // Support Tickets
            'view_support_tickets',
            'create_support_tickets',
            'manage_support_tickets',
            'assign_support_tickets',
            'resolve_support_tickets',

            // Lab Rollovers
            'view_lab_rollovers',
            'retry_lab_rollovers',

            // Admin Panel Access
            'access_admin_panel',
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Define role permissions mapping
        $rolePermissions = [
            'member' => [
                'manage_own_profile',
                'rsvp_events',
                'make_donations',
                'view_forum',
                'create_threads',
                'reply_threads',
                'send_messages',
                'view_messages',
                'view_posts',
                'view_exchange_rates',
                'view_plans',
            ],
            'lab_manager' => [
                'manage_lab_spaces',
                'edit_lab_space',
                'view_all_lab_bookings',
                'manage_lab_maintenance',
                'view_lab_reports',
                'mark_lab_attendance',
                'disable_lab_bookings',
                'enable_lab_bookings',
                'cancel_lab_booking',
                'initiate_lab_refund',
                'view_lab_rollovers',
                'retry_lab_rollovers',
            ],
            'lab_supervisor' => [
                'view_all_lab_bookings',
                'mark_lab_attendance',
                'view_lab_reports',
                'view_lab_rollovers',
                'cancel_lab_booking',
                'initiate_lab_refund',
            ],
            'event_manager' => [
                'create_events',
                'edit_events',
                'delete_events',
                'view_all_events',
                'manage_event_attendees',
            ],
            'content_manager' => [
                'create_posts',
                'edit_posts',
                'publish_posts',
                'delete_posts',
                'view_all_posts',
                'edit_any_post',
                'delete_any_post',
                'manage_post_categories',
                'manage_post_tags',
                'upload_post_media',
                'manage_media',
            ],
            'finance_manager' => [
                'view_donation_ledger',
                'export_donations',
                'reconcile_payments',
                'manage_exchange_rates',
                'refresh_exchange_rates',
                'configure_exchange_rates',
                'view_finance_analytics',
                'manage_payments',
                'manage_refunds',
                'view_reconciliation',
                'manage_reconciliation',
            ],
            'support_agent' => [
                'view_support_tickets',
                'create_support_tickets',
                'manage_support_tickets',
                'assign_support_tickets',
                'resolve_support_tickets',
            ],
            'moderator' => [
                'moderate_forum',
                'lock_threads',
                'pin_threads',
                'delete_any_thread',
                'delete_any_forum_post',
                'ban_forum_users',
                'mute_forum_users',
                'view_forum_reports',
            ],
            'admin' => [
                'manage_users',
                'view_all_users',
                'invite_users',
                'bulk_user_operations',
                'assign_roles',
                'view_audit_logs',
                'manage_permissions',
                'manage_roles',
                'view_reports',
                'manage_system_settings',
                'manage_counties',
                'view_subscriptions',
                'manage_subscriptions',
                'view_student_approvals',
                'approve_student_approvals',
                'reject_student_approvals',
                'manage_retention_settings',
                'manage_groups',
                'manage_group_members',
                'cancel_lab_booking',
                'initiate_lab_refund',
                'manage_lab_spaces',
                'disable_lab_bookings',
                'enable_lab_bookings',
                'view_all_lab_bookings',
                'edit_lab_space',
                'manage_lab_maintenance',
                'view_lab_rollovers',
                'retry_lab_rollovers',
            ],
            'super_admin' => [], // Gets all via syncPermissions logic below
        ];

        // Hierarchical Inheritance logic
        // ------------------------------
        $memberPermissions = $rolePermissions['member'];
        $staffPermissions = array_unique(array_merge($memberPermissions, ['access_admin_panel']));

        // Add 'staff' role if not present
        if (!isset($rolePermissions['staff'])) {
            $rolePermissions['staff'] = $staffPermissions;
        }

        // Ensure all specialized roles inherit staff permissions
        foreach ($rolePermissions as $roleName => &$perms) {
            if (in_array($roleName, ['member', 'staff', 'super_admin'])) {
                continue;
            }
            // All specialized roles get staff capabilities
            $perms = array_unique(array_merge($perms, $staffPermissions));
        }
        unset($perms);

        // Create roles and assign permissions (API guard only)
        foreach ($rolePermissions as $roleName => $rolePerms) {
            $role = Role::updateOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                [
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'is_system' => true,
                    'is_immutable' => in_array($roleName, ['member', 'staff', 'super_admin']),
                ]
            );

            if ($roleName === 'super_admin') {
                // Super admin gets all permissions
                $role->syncPermissions(Permission::where('guard_name', 'web')->get());
            } else {
                $role->syncPermissions(
                    Permission::where('guard_name', 'web')
                        ->whereIn('name', $rolePerms)
                        ->get()
                );
            }
        }

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('Available roles: '.implode(', ', array_keys($rolePermissions)));
    }
}
