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
                'title' => 'Welcome to the Dadisi Community Hub!',
                'content' => "Welcome to the official Dadisi Community Forum! 🌟 This is your central space for collaboration, learning, and local impact across Kenya.\n\nOur mission is to foster a supportive environment where community members can share technical knowledge, coordinate local projects, and stay updated with official announcements. We encourage you to participate actively and help us build a vibrant ecosystem for social and technological innovation.\n\n**Please take a moment to:**\n1. Read our Community Guidelines (pinned in General).\n2. Update your profile with your interests and county.\n3. Introduce yourself in the 'Introduce Yourself' thread!\n\nWe're thrilled to have you here!",
                'category_slug' => 'announcements',
                'pinned' => true,
                'locked' => true,
            ],
            [
                'title' => 'Introduce Yourself & Share Your Journey',
                'content' => "Welcome to the family! 🤝 We believe every member brings unique value to this community. Whether you're a seasoned developer, a local activist, or just starting your journey, we want to hear from you!\n\n**Tell us about:**\n- Your background and skills.\n- What motivated you to join Dadisi.\n- Any projects you're currently working on or dreaming of.\n- Your favorite thing about your county.\n\nDon't be shy—let's build something great together!",
                'category_slug' => 'general-discussion',
                'pinned' => true,
            ],
            // Tech
            [
                'title' => 'Optimal Remote Collaboration Tools for 2025',
                'content' => "With the rise of distributed work, finding the right tools is more critical than ever—especially in areas with varying internet connectivity. 💻\n\nI'm looking for recommendations for project management and communication tools that:\n- Have excellent offline capabilities.\n- Are lightweight and perform well on mobile data.\n- Support seamless synchronization when back online.\n\nCurrently, I'm exploring Obsidian for notes and Trello for tasks. What are your 'must-have' tools for staying productive while working remotely in Kenya?",
                'category_slug' => 'technical-support',
            ],
            [
                'title' => 'Curated React & Modern Web Development Resources',
                'content' => "Learning frontend development can be overwhelming with the constant shift in technologies. 🚀 I've compiled a list of high-quality, free resources that helped me master React and modern CSS:\n\n- **Documentation:** [Official React Docs](https://react.dev) - Always start here!\n- **Interactive Learning:** [FreeCodeCamp](https://www.freecodecamp.org) - Great for fundamental JavaScript.\n- **Design Systems:** [shadcn/ui](https://ui.shadcn.com) - Beautifully designed components.\n- **Community:** [Stack Overflow](https://stackoverflow.com) - For when you get stuck.\n\nWhat other resources have been game-changers for you? Share them below!",
                'category_slug' => 'resources-learning',
            ],
            // County Specific (simulating local hubs)
            [
                'title' => 'Nairobi Tech Hub: Monthly Founders Meetup',
                'content' => "Calling all innovators in the Nairobi metropolitan area! 🏙️ We're organizing a monthly meetup to discuss the challenges and triumphs of building startups in the heart of CBD.\n\n**Details:**\n- **Date:** Last Saturday of the month.\n- **Focus:** Networking, pitch practice, and resource sharing.\n- **Venue:** Rotating between different innovation hubs in Nairobi.\n\nIf you're interested in joining the coordination committee or just want to attend, drop a comment below!",
                'category_slug' => 'events-meetups',
                'county_name' => 'Nairobi',
            ],
            [
                'title' => 'Mombasa Coastal Conservation & Blue Economy Initiative',
                'content' => "Protecting our beautiful coastline is a shared responsibility. 🌊 We are launching a community-led project to monitor coral reef health and coordinate monthly beach plastic cleanups.\n\nWe are looking for:\n- **Volunteers:** For physical cleanup efforts.\n- **Techies:** To help build a simple data collection app for reef health.\n- **Sponsors:** For equipment (gloves, bags, educational materials).\n\nLet's work together to preserve the beauty of Mombasa for generations to come!",
                'category_slug' => 'projects-collaboration',
                'county_name' => 'Mombasa',
            ],
            [
                'title' => 'Kisumu Lakes Economy: Digital Transformation for Fishermen',
                'content' => "Exciting news! We are piloting a digital marketplace to help Kisumu's fisherman connect directly with buyers, ensuring fairer prices and fresher produce. 🐟\n\nWe need help with:\n- Gathering feedback from local cooperatives.\n- Training sessions for mobile app usage.\n- Logistics coordination for local distribution.\n\nIf you're passionate about leveraging technology for local economic growth, we need your expertise!",
                'category_slug' => 'announcements',
                'county_name' => 'Kisumu',
            ],
            [
                'title' => 'Nakuru AgTech: Precision Farming Workshop',
                'content' => "Agriculture is the backbone of Nakuru, and tech is the catalyst for its future. 🚜 Join us for a hands-on workshop dedicated to precision farming techniques using low-cost IoT sensors.\n\n**Topics covered:**\n- Soil moisture monitoring.\n- Weather pattern analysis via mobile apps.\n- Drone mapping for large-scale farms.\n\nThis is a free event for all registered Nakuru farmers. Let's modernize our fields!",
                'category_slug' => 'events-meetups',
                'county_name' => 'Nakuru',
            ],
            [
                'title' => 'Eldoret Innovation: Coding Bootcamp for High School Graduates',
                'content' => "Turning Eldoret into a tech power hub! 🏃‍♂️💨 We are offering a fully-funded, 3-month intensive coding bootcamp for recent high school graduates in Uasin Gishu.\n\nCurriculum includes:\n- HTML, CSS, and Tailwind CSS.\n- JavaScript and React fundamentals.\n- Soft skills for the global digital economy.\n\nApplications are now open. Help us spread the word to the energetic youth of Eldoret!",
                'category_slug' => 'resources-learning',
                'county_name' => 'Uasin Gishu',
            ],
            [
                'title' => 'Machakos Creative Arts: Digital Media Masterclass',
                'content' => "Machakos is home to incredible talent! 🎨 We're hosting a masterclass on digital storytelling and content creation for local artists and filmmakers.\n\nLearn how to:\n- Monetize your creative work online.\n- Use professional editing tools on a budget.\n- Build a strong personal brand on social media.\n\nSeats are limited, so reserve yours today!",
                'category_slug' => 'events-meetups',
                'county_name' => 'Machakos',
            ],
            [
                'title' => 'Turkana Renewable Energy: Community Impact Discussion',
                'content' => "The wind and sun of Turkana are powering the nation. 🌬️☀️ But how is this benefiting the local community? Let's have an open and constructive discussion about renewable energy projects in our county.\n\nGoals of this thread:\n- Share first-hand experiences of living near energy projects.\n- Discuss opportunities for local employment and skill development.\n- Identify areas where community-based tech solutions can improve basic services.\n\nYour voice matters—join the conversation.",
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
                    'content' => $topicData['content'],
                    'is_pinned' => $topicData['pinned'] ?? false,
                    'is_locked' => $topicData['locked'] ?? false,
                    'views_count' => rand(10, 500),
                ]
            );

            // Create Random Replies if thread is empty (excluding OP)
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

            // Sync stats
            $thread->refreshPostStats();
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
