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
            'assign_roles',

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

            // System
            'view_reports',

            // Member access
            'manage_own_profile',
            'rsvp_events',
            'make_donations',
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
                'manage_users', 'view_all_users', 'create_events', 'edit_events',
                'delete_events', 'view_all_events', 'manage_event_attendees',
                'view_donation_ledger', 'export_donations', 'create_posts',
                'edit_posts', 'publish_posts', 'delete_posts', 'view_reports',
            ],
            'finance' => [
                'view_donation_ledger', 'export_donations', 'reconcile_payments', 'view_reports',
            ],
            'events_manager' => [
                'create_events', 'edit_events', 'delete_events', 'view_all_events', 'manage_event_attendees', 'view_reports',
            ],
            'content_editor' => [
                'create_posts', 'edit_posts', 'publish_posts', 'delete_posts',
            ],
            'member' => [
                'manage_own_profile', 'rsvp_events', 'make_donations',
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
