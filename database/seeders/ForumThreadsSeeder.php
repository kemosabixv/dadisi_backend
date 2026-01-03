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
        $counties = County::all(); // Get all counties for local threads
        $groups = \App\Models\Group::all(); // Get all groups to link threads

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
            [
                'title' => 'Kisumu Lakeside Innovation Hub',
                'content' => "Excited to announce a new co-working space opening near the lake! Perfect for remote workers and startups. Grand opening next month.",
                'category_slug' => 'announcements',
                'county_name' => 'Kisumu',
            ],
            [
                'title' => 'Nakuru Agricultural Tech Workshop',
                'content' => "We're organizing a workshop on drone technology for farming. Free entry for all Nakuru residents. Register now!",
                'category_slug' => 'events-meetups',
                'county_name' => 'Nakuru',
            ],
            [
                'title' => 'Uasin Gishu Youth Coding Bootcamp',
                'content' => "Free 3-month coding bootcamp for youth aged 18-25 in Eldoret. Learn web development and get job placement support.",
                'category_slug' => 'resources-learning',
                'county_name' => 'Uasin Gishu',
            ],
            [
                'title' => 'Kiambu Community Garden Project',
                'content' => "Looking for volunteers to help establish a community vegetable garden in Thika. All produce will be shared with local families.",
                'category_slug' => 'projects-collaboration',
                'county_name' => 'Kiambu',
            ],
            [
                'title' => 'Machakos Tech Ladies Meetup',
                'content' => "Monthly meetup for women in tech in Machakos County. Share experiences, network, and learn from each other!",
                'category_slug' => 'events-meetups',
                'county_name' => 'Machakos',
            ],
            [
                'title' => 'Nyeri Coffee Farmers Digital Literacy',
                'content' => "Training program to help coffee farmers use mobile apps for market prices and weather updates. Register your interest!",
                'category_slug' => 'resources-learning',
                'county_name' => 'Nyeri',
            ],
            [
                'title' => 'Garissa Solar Energy Initiative',
                'content' => "Community solar panel installation project for schools in Garissa. Looking for technical volunteers and sponsors.",
                'category_slug' => 'projects-collaboration',
                'county_name' => 'Garissa',
            ],
            [
                'title' => 'Kakamega Forest Conservation Tech',
                'content' => "Using IoT sensors to monitor wildlife in Kakamega Forest. Any developers interested in contributing to the project?",
                'category_slug' => 'technical-support',
                'county_name' => 'Kakamega',
            ],
            [
                'title' => 'Kilifi Beach Tourism App Development',
                'content' => "Building a mobile app to promote local tourism in Kilifi. Need designers and developers to join the team!",
                'category_slug' => 'projects-collaboration',
                'county_name' => 'Kilifi',
            ],
            [
                'title' => 'Baringo Water Management System',
                'content' => "Implementing smart water meters in Baringo to help with conservation. Looking for community feedback on the pilot program.",
                'category_slug' => 'announcements',
                'county_name' => 'Baringo',
            ],
            [
                'title' => 'Bomet Tea Farmers Cooperative Forum',
                'content' => "Discussion thread for tea farmers in Bomet. Share tips, market updates, and connect with fellow farmers.",
                'category_slug' => 'general-discussion',
                'county_name' => 'Bomet',
            ],
            [
                'title' => 'Kajiado Maasai Cultural Tech Initiative',
                'content' => "Preserving Maasai heritage through digital storytelling. Looking for content creators and translators.",
                'category_slug' => 'projects-collaboration',
                'county_name' => 'Kajiado',
            ],
            [
                'title' => 'Turkana Renewable Energy Discussion',
                'content' => "Let's discuss the wind power projects in Turkana and their impact on local communities.",
                'category_slug' => 'general-discussion',
                'county_name' => 'Turkana',
            ],
        ];

        foreach ($topics as $topicData) {
            $user = $users->random();
            $category = $categories->where('slug', $topicData['category_slug'])->first();

            if (!$category) continue;

            $countyId = null;
            $groupId = null;
            if (isset($topicData['county_name'])) {
                $county = $counties->where('name', $topicData['county_name'])->first();
                $countyId = $county?->id;
                
                if ($countyId) {
                    $group = $groups->where('county_id', $countyId)->first();
                    $groupId = $group?->id;
                }
            }

            // Create Thread
            $thread = ForumThread::updateOrCreate(
                ['slug' => Str::slug($topicData['title'])],
                [
                    'category_id' => $category->id,
                    'county_id' => $countyId,
                    'group_id' => $groupId,
                    'user_id' => $user->id,
                    'title' => $topicData['title'],
                    'is_pinned' => $topicData['pinned'] ?? false,
                    'is_locked' => $topicData['locked'] ?? false,
                    'views_count' => rand(10, 500),
                ]
            );

            // Create Initial Post (OP) if not exists
            if ($thread->posts()->count() === 0) {
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
