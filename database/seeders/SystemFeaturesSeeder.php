<?php

namespace Database\Seeders;

use App\Models\SystemFeature;
use Illuminate\Database\Seeder;

/**
 * Seeds the built-in system features.
 * 
 * These features are:
 * - Built-in (cannot be deleted by users)
 * - Managed by the system
 * - Associated with plans via the plan dialog
 */
class SystemFeaturesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            // Event-related features
            [
                'slug' => 'event_creation_limit',
                'name' => 'Event Creation Limit',
                'description' => 'Maximum events a user can create per month. Set to -1 for unlimited.',
                'value_type' => 'number',
                'default_value' => '0',
                'sort_order' => 1,
            ],
            [
                'slug' => 'ticket_discount_percent',
                'name' => 'Ticket Discount Percent',
                'description' => 'Percentage discount on event ticket purchases for subscribers.',
                'value_type' => 'number',
                'default_value' => '0',
                'sort_order' => 2,
            ],
            [
                'slug' => 'priority_event_access',
                'name' => 'Priority Event Access',
                'description' => 'Early access to event registrations before general availability.',
                'value_type' => 'boolean',
                'default_value' => 'false',
                'sort_order' => 3,
            ],
            
            // Lab booking features
            [
                'slug' => 'lab_hours_monthly',
                'name' => 'Lab Hours Monthly',
                'description' => 'Maximum lab booking hours per month. Set to -1 for unlimited, 0 for no access.',
                'value_type' => 'number',
                'default_value' => '0',
                'sort_order' => 10,
            ],
            [
                'slug' => 'lab_auto_approve',
                'name' => 'Lab Auto-Approve',
                'description' => 'Lab bookings are automatically approved without admin review.',
                'value_type' => 'boolean',
                'default_value' => 'false',
                'sort_order' => 11,
            ],
            
            // Blog features
            [
                'slug' => 'blog_creation_limit',
                'name' => 'Blog Post Limit',
                'description' => 'Maximum blog posts a user can create per month. Set to -1 for unlimited.',
                'value_type' => 'number',
                'default_value' => '0',
                'sort_order' => 20,
            ],
            
            // Forum features
            [
                'slug' => 'private_message_limit',
                'name' => 'Private Message Limit',
                'description' => 'Maximum private messages per month. Set to -1 for unlimited.',
                'value_type' => 'number',
                'default_value' => '0',
                'sort_order' => 30,
            ],
            [
                'slug' => 'forum_thread_limit',
                'name' => 'Forum Thread Limit',
                'description' => 'Maximum forum threads a user can create per month. Set to -1 for unlimited.',
                'value_type' => 'number',
                'default_value' => '0',
                'sort_order' => 31,
            ],
            
            // Premium features
            [
                'slug' => 'early_access',
                'name' => 'Early Access',
                'description' => 'Access to beta features and early releases.',
                'value_type' => 'boolean',
                'default_value' => 'false',
                'sort_order' => 100,
            ],
            [
                'slug' => 'dedicated_support',
                'name' => 'Dedicated Support',
                'description' => 'Access to priority support channels.',
                'value_type' => 'boolean',
                'default_value' => 'false',
                'sort_order' => 101,
            ],
        ];

        foreach ($features as $feature) {
            SystemFeature::updateOrCreate(
                ['slug' => $feature['slug']],
                array_merge($feature, ['is_active' => true])
            );
        }

        // Remove deprecated features
        $deprecatedSlugs = ['event_ticket_discount', 'lab_booking_limit'];
        SystemFeature::whereIn('slug', $deprecatedSlugs)->delete();

        $this->command->info('System features seeded successfully.');
    }
}
