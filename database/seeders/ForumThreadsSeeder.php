<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ForumThreadsSeeder extends Seeder
{
    /**
     * Seed the forum threads and posts.
     */
    public function run(): void
    {
        $users = User::limit(10)->get();
        if ($users->isEmpty()) {
            return;
        }

        $categories = ForumCategory::all();
        $counties = County::limit(5)->get(); // Get a few counties for local threads

        // Topics to seed
        $topics = [
            // General
            [
                'title' => 'Welcome to the new Dadisi Community Forum!',
                'content' => "We are excited to launch this new space for our community members to connect, share ideas, and collaborate on projects. \n\nPlease read the community guidelines before posting.",
                'category_slug' => 'announcements',
                'pinned' => true,
                'locked' => true,
            ],
            [
                'title' => 'Introduce Yourself Here',
                'content' => "New to the platform? Tell us a bit about yourself, your background, and what you're working on!",
                'category_slug' => 'general-discussion',
                'pinned' => true,
            ],
            // Tech
            [
                'title' => 'Best tools for remote collaboration in 2025?',
                'content' => "I'm looking for recommendations for project management tools that work well with spotty internet connections. What are you all using?",
                'category_slug' => 'technical-support',
            ],
            [
                'title' => 'Sharing my React learning resources',
                'content' => "Here is a list of free resources I found helpful while learning frontend development:\n- MDN Docs\n- FreeCodeCamp\n- React Patterns\n\nFeel free to add yours!",
                'category_slug' => 'resources-learning',
            ],
            // County Specific (simulating local hubs)
            [
                'title' => 'Tech Meetup in Nairobi CBD',
                'content' => "Hey everyone, we're planning a small meetup for developers in Nairobi next Saturday. Who's interested?",
                'category_slug' => 'events-meetups',
                'county_name' => 'Nairobi',
            ],
            [
                'title' => 'Mombasa Coastal Cleanup Project',
                'content' => "Looking for volunteers to help coordinate the monthly beach cleanup. We need people to help with logistics and social media.",
                'category_slug' => 'projects-collaboration',
                'county_name' => 'Mombasa',
            ],
        ];

        foreach ($topics as $topicData) {
            $user = $users->random();
            $category = $categories->where('slug', $topicData['category_slug'])->first();

            if (!$category) continue;

            $countyId = null;
            if (isset($topicData['county_name'])) {
                $county = $counties->where('name', $topicData['county_name'])->first();
                $countyId = $county?->id;
            }

            // Create Thread
            $thread = ForumThread::create([
                'category_id' => $category->id,
                'county_id' => $countyId,
                'user_id' => $user->id,
                'title' => $topicData['title'],
                'slug' => Str::slug($topicData['title']) . '-' . Str::random(6),
                'is_pinned' => $topicData['pinned'] ?? false,
                'is_locked' => $topicData['locked'] ?? false,
                'views_count' => rand(10, 500),
            ]);

            // Create Initial Post (OP)
            $thread->posts()->create([
                'user_id' => $user->id,
                'content' => $topicData['content'],
            ]);

            // Create Random Replies
            if (!($topicData['locked'] ?? false)) {
                $replyCount = rand(2, 8);
                for ($i = 0; $i < $replyCount; $i++) {
                    $replier = $users->random();
                    $thread->posts()->create([
                        'user_id' => $replier->id,
                        'content' => $this->getRandomReply(),
                    ]);
                }
            }
        }
    }

    private function getRandomReply(): string
    {
        $replies = [
            "This is great info, thanks for sharing!",
            "I totally agree with this point.",
            "Can you elaborate more on that?",
            "I've had a similar experience in my county.",
            "Count me in!",
            "This sounds like an amazing initiative.",
            "Thanks for the update.",
            "Following this thread.",
            "Has anyone tried using X for this instead?",
            "Looking forward to seeing how this develops.",
        ];

        return $replies[array_rand($replies)];
    }
}
