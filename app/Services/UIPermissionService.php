<?php

namespace App\Services;

use App\Models\User;

class UIPermissionService
{
    private $user;
    
    public function __construct(User $user)
    {
        $this->user = $user;
    }
    
    public function getUIPermissions(): array
    {
        return [
            // User Management
            'can_view_users' => $this->user->can('view_all_users'),
            'can_create_users' => $this->user->can('manage_users'),
            'can_edit_users' => $this->user->can('manage_users'),
            'can_delete_users' => $this->user->can('manage_users'),
            'can_assign_roles' => $this->user->can('assign_roles'),
            'can_invite_users' => $this->user->can('invite_users'),
            'can_bulk_manage_users' => $this->user->can('bulk_user_operations'),
            'can_view_audit_logs' => $this->user->can('view_audit_logs'),
            
            // Event Management
            'can_view_events' => $this->user->can('view_all_events'),
            'can_create_events' => $this->user->can('create_events'),
            'can_edit_events' => $this->user->can('edit_events'),
            'can_delete_events' => $this->user->can('delete_events'),
            'can_manage_event_attendees' => $this->user->can('manage_event_attendees'),
            
            // Content Management
            'can_manage_blog' => $this->user->can('view_all_posts') || $this->user->can('edit_any_post'),
            'can_create_posts' => $this->user->can('create_posts'),
            'can_manage_pages' => false, // Feature not yet in seeder
            'can_manage_media' => $this->user->can('manage_media'),
            
            // Financial
            'can_view_donations' => $this->user->can('view_donation_ledger'),
            'can_manage_donations' => $this->user->can('reconcile_payments'),
            'can_export_donations' => $this->user->can('export_donations'),
            
            // System
            'can_manage_roles' => $this->user->can('manage_roles'),
            'can_manage_settings' => $this->user->can('configure_exchange_rates'), // Proxy for system settings
            'can_view_reports' => $this->user->can('view_reports'),
            'can_manage_plans' => $this->user->can('manage_plans'),
            
            // Lab Space Booking
            'can_view_lab_spaces' => $this->user->can('view_all_lab_bookings') || $this->user->can('manage_lab_spaces'),
            'can_manage_lab_spaces' => $this->user->can('manage_lab_spaces'),
            'can_view_lab_bookings' => $this->user->can('view_all_lab_bookings'),
            'can_approve_lab_bookings' => $this->user->can('approve_lab_bookings'),
            'can_manage_lab_maintenance' => $this->user->can('manage_lab_maintenance'),
            'can_view_lab_reports' => $this->user->can('view_lab_reports'),
            'can_mark_lab_attendance' => $this->user->can('mark_lab_attendance'),
            
            // Forum Management
            'can_moderate_forum' => $this->user->can('moderate_forum'),
            'can_manage_forum_tags' => $this->user->can('manage_forum_tags'),
            'can_manage_forum_categories' => $this->user->can('manage_forum_categories'),
            'can_manage_groups' => $this->user->can('manage_groups'),

            // General Admin
            'can_access_admin_panel' => $this->user->canAccessAdminPanel(),
        ];
    }
    
    public function getAuthorizedMenu(): array
    {
        $menu = [];
        
        // Only generate menu if user can access admin panel
        if (!$this->user->canAccessAdminPanel()) {
            return [];
        }

        // Dashboard - always for admin users
        $menu[] = ['title' => 'Dashboard', 'path' => '/admin', 'icon' => 'dashboard'];
        
        // Users
        if ($this->user->can('view_all_users') || $this->user->can('manage_users')) {
            $menu[] = [
                'title' => 'Users',
                'path' => '/admin/users',
                'icon' => 'users',
                'badge' => $this->user->can('manage_users') ? 'manage' : null,
            ];
        }
        
        // Events
        if ($this->user->can('view_all_events')) {
            $menu[] = [
                'title' => 'Events',
                'path' => '/admin/events',
                'icon' => 'calendar',
                'badge' => $this->user->can('create_events') ? 'create' : null,
            ];
        }
        
        // Content
        if ($this->user->can('view_all_posts') || $this->user->can('create_posts')) {
             $menu[] = [
                'title' => 'Content',
                'path' => '/admin/blog', // Updated path to actual blog admin
                'icon' => 'file-text',
            ];
        }

        // Roles (only if can manage roles)
        if ($this->user->can('manage_roles')) {
            $menu[] = [
                'title' => 'Roles & Permissions',
                'path' => '/admin/roles',
                'icon' => 'shield',
            ];
        }
        
        // Donations
        if ($this->user->can('view_donation_ledger')) {
            $menu[] = [
                'title' => 'Donations',
                'path' => '/admin/donations',
                'icon' => 'dollar-sign',
            ];
        }

        // Plans & Subscriptions
        if ($this->user->can('manage_plans') || $this->user->can('view_plans')) {
            $menu[] = [
                'title' => 'Plans',
                'path' => '/admin/plans',
                'icon' => 'credit-card',
            ];
        }

        // Reports
        if ($this->user->can('view_reports')) {
             $menu[] = [
                'title' => 'Reports',
                'path' => '/admin/reports',
                'icon' => 'bar-chart',
            ];
        }

        // Lab Spaces
        if ($this->user->can('view_all_lab_bookings') || $this->user->can('manage_lab_spaces')) {
            $menu[] = [
                'title' => 'Lab Spaces',
                'path' => '/admin/spaces',
                'icon' => 'flask',
                'badge' => $this->user->can('manage_lab_spaces') ? 'manage' : null,
            ];
        }

        // Settings
        if ($this->user->can('configure_exchange_rates')) {
            $menu[] = [
                'title' => 'Settings',
                'path' => '/admin/settings',
                'icon' => 'settings',
            ];
        }
        
        return $menu;
    }
}
