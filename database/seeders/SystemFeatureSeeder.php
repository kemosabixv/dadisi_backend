<?php

namespace Database\Seeders;

use App\Models\SystemFeature;
use Illuminate\Database\Seeder;

class SystemFeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            // Event-related features
            [
                'slug' => 'ticket_discount_percent',
                'name' => 'Ticket Discount Percent',
                'description' => 'Percentage discount on event ticket purchases for subscribers.',
                'value_type' => 'number',
                'default_value' => '0',
                'sort_order' => 2,
            ],
            [
                'slug' => 'waitlist_priority',
                'name' => 'Priority Event Access (Waitlist priority)',
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

            // Blog features
            [
                'name' => 'Monthly Blog Posts',
                'slug' => 'blog-posts-monthly',
                'description' => 'Number of blog posts a user can publish per month.',
                'value_type' => 'number',
                'default_value' => '0',
                'sort_order' => 21,
            ],

            // Forum features
            [
                'slug' => 'monthly_chat_message_limit',
                'name' => 'Monthly Chat Message Limit',
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
            [
                'slug' => 'forum_reply_limit',
                'name' => 'Forum Reply Limit',
                'description' => 'Maximum forum replies a user can post per month. Set to -1 for unlimited.',
                'value_type' => 'number',
                'default_value' => '0',
                'sort_order' => 32,
            ],
            [
                'name' => 'Forum Access',
                'slug' => 'forum-access',
                'description' => 'Ability to read and post in community forums.',
                'value_type' => 'boolean',
                'default_value' => 'true',
                'sort_order' => 33,
            ],

            // Compatibility / Legacy
            [
                'name' => 'Media Storage (MB)',
                'slug' => 'media-storage-mb',
                'description' => 'Total cloud storage capacity in megabytes.',
                'value_type' => 'number',
                'default_value' => '50',
                'sort_order' => 110,
            ],
            [
                'name' => 'Max Upload Size (MB)',
                'slug' => 'media-max-upload-mb',
                'description' => 'Maximum single file upload size in megabytes.',
                'value_type' => 'number',
                'default_value' => '5',
                'sort_order' => 111,
            ],
        ];

        foreach ($features as $feature) {
            SystemFeature::updateOrCreate(
                ['slug' => $feature['slug']],
                array_merge($feature, ['is_active' => true])
            );
        }

        // Remove any other features NOT in this list if they are system features
        // SystemFeature::whereNotIn('slug', array_column($features, 'slug'))->delete();

        $this->command->info('System features normalized in SystemFeatureSeeder.');
    }
}
