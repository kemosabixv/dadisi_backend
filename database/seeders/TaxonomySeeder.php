<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaxonomySeeder extends Seeder
{
    /**
     * Seed categories and tags.
     */
    public function run(): void
    {
        // 1. Seed Categories
        $categories = [
            'Technology',
            'Agriculture',
            'Education',
            'Health',
            'Governance',
            'Environment',
            'Business',
            'Culture',
            'Sports'
        ];

        foreach ($categories as $name) {
            $slug = Str::slug($name);
            DB::table('categories')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 2. Seed Tags
        $tags = [
            'Sustainability',
            'Innovation',
            'Youth Empowerment',
            'Funding Opportunities',
            'Policy',
            'Community Events',
            'Success Stories',
            'Digital Transformation',
            'Climate Action',
            'Mental Health',
            'Startups',
            'Civic Tech'
        ];

        foreach ($tags as $name) {
            $slug = Str::slug($name);
            DB::table('tags')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
