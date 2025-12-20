<?php

namespace Database\Seeders;

use App\Models\ForumCategory;
use Illuminate\Database\Seeder;

class ForumCategoriesSeeder extends Seeder
{
    /**
     * Seed the forum categories.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Announcements',
                'slug' => 'announcements',
                'description' => 'Official announcements from Dadisi Community Labs.',
                'icon' => 'megaphone',
                'order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'General Discussion',
                'slug' => 'general-discussion',
                'description' => 'Open discussions on any topic related to our community.',
                'icon' => 'message-circle',
                'order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Events & Meetups',
                'slug' => 'events-meetups',
                'description' => 'Discuss upcoming events, share experiences, and coordinate meetups.',
                'icon' => 'calendar',
                'order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Projects & Collaboration',
                'slug' => 'projects-collaboration',
                'description' => 'Share projects, find collaborators, and get feedback.',
                'icon' => 'users',
                'order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Technical Support',
                'slug' => 'technical-support',
                'description' => 'Get help with technical issues and share solutions.',
                'icon' => 'help-circle',
                'order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Resources & Learning',
                'slug' => 'resources-learning',
                'description' => 'Share educational resources, tutorials, and learning materials.',
                'icon' => 'book-open',
                'order' => 6,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            ForumCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
