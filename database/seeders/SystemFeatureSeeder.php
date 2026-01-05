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
            [
                'name' => 'Monthly Event Participations',
                'slug' => 'event-participations-monthly',
                'description' => 'Number of events a user can participate in per month.',
                'value_type' => 'number',
                'default_value' => '0',
                'sort_order' => 1,
            ],
            [
                'name' => 'Event Discount Percent',
                'slug' => 'event-discount-percent',
                'description' => 'Discount percentage applied to paid events.',
                'value_type' => 'number',
                'default_value' => '0',
                'sort_order' => 2,
            ],
            [
                'name' => 'Monthly Blog Posts',
                'slug' => 'blog-posts-monthly',
                'description' => 'Number of blog posts a user can publish per month.',
                'value_type' => 'number',
                'default_value' => '0',
                'sort_order' => 3,
            ],
            [
                'name' => 'Monthly Lab Hours',
                'slug' => 'lab-hours-monthly',
                'description' => 'Maximum lab hours that can be booked per month.',
                'value_type' => 'number',
                'default_value' => '0',
                'sort_order' => 4,
            ],
            [
                'name' => 'Research Collaborators',
                'slug' => 'research-collaborators',
                'description' => 'Number of researchers the user can collaborate with.',
                'value_type' => 'number',
                'default_value' => '0',
                'sort_order' => 5,
            ],
            [
                'name' => 'Forum Access',
                'slug' => 'forum-access',
                'description' => 'Ability to read and post in community forums.',
                'value_type' => 'boolean',
                'default_value' => 'true',
                'sort_order' => 6,
            ],
            [
                'name' => 'Lab Access',
                'slug' => 'lab-access',
                'description' => 'Ability to book and access lab spaces.',
                'value_type' => 'boolean',
                'default_value' => 'false',
                'sort_order' => 7,
            ],
            [
                'name' => 'Mentorship Access',
                'slug' => 'mentorship-access',
                'description' => 'Ability to request mentorship from experts.',
                'value_type' => 'boolean',
                'default_value' => 'false',
                'sort_order' => 8,
            ],
            [
                'name' => 'Certificate Programs',
                'slug' => 'certificate-programs',
                'description' => 'Access to certification tracks and progress tracking.',
                'value_type' => 'boolean',
                'default_value' => 'false',
                'sort_order' => 9,
            ],
            [
                'name' => 'API Access',
                'slug' => 'api-access',
                'description' => 'Programmatic access to the Dadisi platform APIs.',
                'value_type' => 'boolean',
                'default_value' => 'false',
                'sort_order' => 10,
            ],
            [
                'name' => 'Sample Tracking',
                'slug' => 'sample-tracking-limit',
                'description' => 'Number of biological samples that can be tracked in the system.',
                'value_type' => 'number',
                'default_value' => '0',
                'sort_order' => 11,
            ],
            [
                'name' => 'Priority Support',
                'slug' => 'priority-support',
                'description' => 'Faster response times for support inquiries.',
                'value_type' => 'boolean',
                'default_value' => 'false',
                'sort_order' => 12,
            ],
            [
                'name' => 'Media Storage (MB)',
                'slug' => 'media-storage-mb',
                'description' => 'Total cloud storage capacity in megabytes.',
                'value_type' => 'number',
                'default_value' => '50',
                'sort_order' => 13,
            ],
            [
                'name' => 'Max Upload Size (MB)',
                'slug' => 'media-max-upload-mb',
                'description' => 'Maximum single file upload size in megabytes.',
                'value_type' => 'number',
                'default_value' => '5',
                'sort_order' => 14,
            ],
        ];

        foreach ($features as $feature) {
            SystemFeature::updateOrCreate(
                ['slug' => $feature['slug']],
                $feature
            );
        }
    }
}
