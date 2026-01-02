<?php

namespace Database\Seeders;

use App\Models\ForumTag;
use App\Models\ForumThread;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ForumTagsSeeder extends Seeder
{
    /**
     * Seed forum tags and attach them to existing threads.
     */
    public function run(): void
    {
        $tags = [
            ['name' => 'Biotech', 'color' => '#6366f1', 'description' => 'Biotechnology discussions'],
            ['name' => 'Events', 'color' => '#10b981', 'description' => 'Community events and meetups'],
            ['name' => 'Learning', 'color' => '#f59e0b', 'description' => 'Educational resources and tutorials'],
            ['name' => 'Collaboration', 'color' => '#3b82f6', 'description' => 'Project collaboration opportunities'],
            ['name' => 'Tech', 'color' => '#8b5cf6', 'description' => 'Technology and tools'],
            ['name' => 'Local', 'color' => '#ec4899', 'description' => 'Local community initiatives'],
            ['name' => 'Help', 'color' => '#ef4444', 'description' => 'Requests for help or support'],
            ['name' => 'News', 'color' => '#14b8a6', 'description' => 'Community news and updates'],
        ];

        foreach ($tags as $tagData) {
            ForumTag::updateOrCreate(
                ['slug' => Str::slug($tagData['name'])],
                [
                    'name' => $tagData['name'],
                    'slug' => Str::slug($tagData['name']),
                    'color' => $tagData['color'],
                    'description' => $tagData['description'],
                    'usage_count' => 0,
                ]
            );
        }

        // Attach some tags to existing threads
        $threads = ForumThread::all();
        $allTags = ForumTag::all();

        if ($threads->isNotEmpty() && $allTags->isNotEmpty()) {
            foreach ($threads as $thread) {
                // Randomly attach 1-3 tags to each thread
                $randomTags = $allTags->random(min(rand(1, 3), $allTags->count()));
                $thread->tags()->syncWithoutDetaching($randomTags->pluck('id')->toArray());
            }

            // Update usage counts
            foreach ($allTags as $tag) {
                $tag->update(['usage_count' => $tag->threads()->count()]);
            }
        }
    }
}
