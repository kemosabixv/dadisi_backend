<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventTag;
use App\Models\Ticket;
use App\Models\Speaker;
use App\Models\County;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SampleEventsSeeder extends Seeder
{
    public function run(): void
    {
        // Get reference data
        $nairobiCounty = County::where('name', 'Nairobi')->first() ?? County::first();
        $mombasaCounty = County::where('name', 'Mombasa')->first() ?? $nairobiCounty;
        $kisumu = County::where('name', 'Kisumu')->first() ?? $nairobiCounty;
        
        $categories = EventCategory::all()->keyBy('slug');
        $tags = EventTag::all()->keyBy('slug');
        
        // Get or create an organizer user
        $organizer = User::where('email', 'admin@dadisilab.com')->first() 
            ?? User::first() 
            ?? User::factory()->create(['name' => 'Event Organizer', 'email' => 'organizer@dadisilab.com']);

        $now = Carbon::now();

        // Define events
        $eventsData = [
            [
                'title' => 'Community Science Day 2025',
                'description' => 'Join us for an exciting day of hands-on science experiments and demonstrations. Perfect for families and science enthusiasts of all ages. Learn about biotechnology, environmental science, and community-driven research projects.',
                'category' => 'community-science',
                'venue' => 'Dadisi Community Lab, Westlands',
                'is_online' => false,
                'county' => $nairobiCounty,
                'capacity' => 150,
                'price' => 0,
                'status' => 'published',
                'featured' => true,
                'starts_at' => $now->copy()->addWeeks(2)->setTime(9, 0),
                'ends_at' => $now->copy()->addWeeks(2)->setTime(17, 0),
                'tags' => ['in-person', 'free', 'beginner'],
                'tickets' => [
                    ['name' => 'General Admission', 'price' => 0, 'capacity' => 100],
                    ['name' => 'VIP (Includes Lunch)', 'price' => 500, 'capacity' => 50],
                ],
                'speakers' => [
                    ['name' => 'Dr. Amani Ochieng', 'designation' => 'Lead Scientist', 'company' => 'Dadisi Labs', 'bio' => 'Molecular biologist with 15 years of experience in community science education.'],
                    ['name' => 'Prof. Wanjiku Mwangi', 'designation' => 'Environmental Researcher', 'company' => 'University of Nairobi', 'bio' => 'Expert in sustainable agriculture and community-based environmental monitoring.'],
                ],
            ],
            [
                'title' => 'Biotech Workshop: PCR Fundamentals',
                'description' => 'Learn the fundamentals of Polymerase Chain Reaction (PCR), a cornerstone technique in molecular biology. This hands-on workshop covers DNA extraction, PCR setup, and result analysis.',
                'category' => 'biotech-health',
                'venue' => 'Dadisi Community Lab, Westlands',
                'is_online' => false,
                'online_link' => 'https://zoom.us/j/123456789',
                'county' => $nairobiCounty,
                'capacity' => 30,
                'price' => 1500,
                'status' => 'published',
                'featured' => false,
                'starts_at' => $now->copy()->addWeeks(3)->setTime(10, 0),
                'ends_at' => $now->copy()->addWeeks(3)->setTime(16, 0),
                'tags' => ['in-person', 'paid', 'advanced', 'workshop'],
                'tickets' => [
                    ['name' => 'Early Bird', 'price' => 1200, 'capacity' => 10, 'available_until' => $now->copy()->addWeeks(2)],
                    ['name' => 'Regular', 'price' => 1500, 'capacity' => 20],
                ],
                'speakers' => [
                    ['name' => 'Dr. Kipchoge Mutai', 'designation' => 'Biotech Instructor', 'company' => 'Dadisi Labs', 'bio' => 'Specializes in molecular diagnostics and lab training.'],
                ],
            ],
            [
                'title' => 'Online Python for Data Science',
                'description' => 'A comprehensive 4-hour online bootcamp covering Python programming for data science. Topics include pandas, numpy, matplotlib, and basic machine learning concepts.',
                'category' => 'technology-coding',
                'venue' => null,
                'is_online' => true,
                'online_link' => 'https://meet.google.com/abc-defg-hij',
                'county' => null,
                'capacity' => 200,
                'price' => 1000,
                'status' => 'published',
                'featured' => false,
                'starts_at' => $now->copy()->addWeeks(1)->setTime(14, 0),
                'ends_at' => $now->copy()->addWeeks(1)->setTime(18, 0),
                'tags' => ['online', 'paid', 'beginner'],
                'tickets' => [
                    ['name' => 'Standard Access', 'price' => 1000, 'capacity' => 150],
                    ['name' => 'Premium (Recording + Certificate)', 'price' => 2000, 'capacity' => 50],
                ],
                'speakers' => [
                    ['name' => 'Jane Akinyi', 'designation' => 'Senior Developer', 'company' => 'TechHub Kenya', 'bio' => 'Full-stack developer and data science educator.'],
                ],
            ],
            [
                'title' => 'Environmental Awareness Walk',
                'description' => 'Join the community for a guided nature walk through Karura Forest. Learn about local ecosystems, conservation efforts, and how you can contribute to environmental protection.',
                'category' => 'environmental-science',
                'venue' => 'Karura Forest Main Gate',
                'is_online' => false,
                'county' => $nairobiCounty,
                'capacity' => 50,
                'price' => 0,
                'status' => 'draft',
                'featured' => false,
                'starts_at' => $now->copy()->addWeeks(4)->setTime(7, 0),
                'ends_at' => $now->copy()->addWeeks(4)->setTime(12, 0),
                'tags' => ['in-person', 'free', 'beginner'],
                'tickets' => [
                    ['name' => 'Free Entry', 'price' => 0, 'capacity' => 50],
                ],
                'speakers' => [],
            ],
            [
                'title' => 'Advanced Data Science with R',
                'description' => 'Deep dive into statistical analysis and machine learning using R. This advanced workshop covers regression, classification, and data visualization techniques.',
                'category' => 'technology-coding',
                'venue' => null,
                'is_online' => true,
                'online_link' => 'https://zoom.us/j/987654321',
                'county' => null,
                'capacity' => 100,
                'price' => 2500,
                'status' => 'published',
                'featured' => false,
                'starts_at' => $now->copy()->addWeeks(5)->setTime(9, 0),
                'ends_at' => $now->copy()->addWeeks(5)->setTime(17, 0),
                'tags' => ['online', 'paid', 'advanced'],
                'tickets' => [
                    ['name' => 'Full Day Access', 'price' => 2500, 'capacity' => 100],
                ],
                'speakers' => [
                    ['name' => 'Dr. Fatima Hassan', 'designation' => 'Data Scientist', 'company' => 'Analytics Africa', 'bio' => 'PhD in Statistics with expertise in biostatistics.'],
                ],
            ],
            [
                'title' => 'Youth Tech Camp: Intro to Robotics',
                'description' => 'An exciting robotics camp for young people aged 12-18. Participants will learn basic programming and build their own simple robots using Arduino.',
                'category' => 'education-tutorials',
                'venue' => 'Kisumu Innovation Hub',
                'is_online' => false,
                'county' => $kisumu,
                'capacity' => 40,
                'price' => 200,
                'status' => 'published',
                'featured' => true,
                'starts_at' => $now->copy()->addWeeks(2)->addDays(1)->setTime(9, 0),
                'ends_at' => $now->copy()->addWeeks(2)->addDays(1)->setTime(15, 0),
                'tags' => ['in-person', 'beginner', 'workshop'],
                'tickets' => [
                    ['name' => 'Student Ticket', 'price' => 200, 'capacity' => 40],
                ],
                'speakers' => [
                    ['name' => 'Moses Otieno', 'designation' => 'Robotics Instructor', 'company' => 'Kisumu Innovation Hub', 'bio' => 'Passionate about introducing young people to STEM.'],
                ],
            ],
            [
                'title' => 'Past: Community Meetup - December',
                'description' => 'Our monthly community meetup where members share updates, network, and plan upcoming activities. This was a great session with over 50 attendees.',
                'category' => 'community-science',
                'venue' => 'Dadisi Community Lab, Westlands',
                'is_online' => false,
                'county' => $nairobiCounty,
                'capacity' => 80,
                'price' => 0,
                'status' => 'published',
                'featured' => false,
                'starts_at' => $now->copy()->subWeeks(1)->setTime(18, 0),
                'ends_at' => $now->copy()->subWeeks(1)->setTime(20, 0),
                'tags' => ['in-person', 'free'],
                'tickets' => [
                    ['name' => 'Free Entry', 'price' => 0, 'capacity' => 80],
                ],
                'speakers' => [],
            ],
            [
                'title' => 'Innovation Summit 2025',
                'description' => 'The premier innovation event of the year! Join industry leaders, startups, and researchers for a day of presentations, panels, and networking. Hybrid event with both in-person and online options.',
                'category' => 'workshops-hands-on',
                'venue' => 'Kenyatta International Convention Centre',
                'is_online' => false,
                'online_link' => 'https://summit.dadisilab.com/live',
                'county' => $nairobiCounty,
                'capacity' => 500,
                'price' => 1500,
                'status' => 'published',
                'featured' => true,
                'featured_until' => $now->copy()->addMonths(2),
                'starts_at' => $now->copy()->addWeeks(6)->setTime(8, 0),
                'ends_at' => $now->copy()->addWeeks(6)->setTime(18, 0),
                'tags' => ['hybrid', 'paid', 'conference'],
                'tickets' => [
                    ['name' => 'Online Pass', 'price' => 500, 'capacity' => 300],
                    ['name' => 'In-Person Standard', 'price' => 1500, 'capacity' => 150],
                    ['name' => 'In-Person VIP', 'price' => 5000, 'capacity' => 50],
                ],
                'speakers' => [
                    ['name' => 'Dr. Wangari Maathai Jr.', 'designation' => 'Keynote Speaker', 'company' => 'Green Belt Movement', 'bio' => 'Environmental activist and sustainability advocate.', 'is_featured' => true],
                    ['name' => 'James Mwangi', 'designation' => 'CEO', 'company' => 'Equity Bank', 'bio' => 'Banking and fintech pioneer in East Africa.', 'is_featured' => true],
                    ['name' => 'Lupita Nyong\'o', 'designation' => 'Arts & Culture Panel', 'company' => 'Hollywood Actress', 'bio' => 'Oscar-winning actress and arts advocate.', 'is_featured' => true],
                ],
            ],
            [
                'title' => 'Coastal Health Outreach',
                'description' => 'Community health screening and awareness event in Mombasa. Free blood pressure checks, diabetes screening, and health education sessions.',
                'category' => 'biotech-health',
                'venue' => 'Mombasa County Hall',
                'is_online' => false,
                'county' => $mombasaCounty,
                'capacity' => 200,
                'price' => 0,
                'status' => 'published',
                'featured' => false,
                'starts_at' => $now->copy()->addWeeks(3)->addDays(2)->setTime(8, 0),
                'ends_at' => $now->copy()->addWeeks(3)->addDays(2)->setTime(16, 0),
                'tags' => ['in-person', 'free', 'beginner'],
                'tickets' => [
                    ['name' => 'Health Screening Pass', 'price' => 0, 'capacity' => 200],
                ],
                'speakers' => [
                    ['name' => 'Dr. Halima Omar', 'designation' => 'County Health Director', 'company' => 'Mombasa County', 'bio' => 'Public health expert with 20 years of experience.'],
                ],
            ],
        ];

        foreach ($eventsData as $eventData) {
            $category = $categories->get($eventData['category'] ?? null);
            
            $event = Event::firstOrCreate(
                ['slug' => Str::slug($eventData['title'])],
                [
                    'title' => $eventData['title'],
                    'description' => $eventData['description'],
                    'category_id' => $category?->id,
                    'venue' => $eventData['venue'],
                    'is_online' => $eventData['is_online'],
                    'online_link' => $eventData['online_link'] ?? null,
                    'county_id' => $eventData['county']?->id,
                    'capacity' => $eventData['capacity'],
                    'waitlist_enabled' => ($eventData['capacity'] ?? 0) > 0,
                    'waitlist_capacity' => 20,
                    'price' => $eventData['price'],
                    'currency' => 'KES',
                    'status' => $eventData['status'],
                    'featured' => $eventData['featured'] ?? false,
                    'featured_until' => $eventData['featured_until'] ?? null,
                    'organizer_id' => $organizer->id,
                    'created_by' => $organizer->id,
                    'published_at' => $eventData['status'] === 'published' ? now() : null,
                    'starts_at' => $eventData['starts_at'],
                    'ends_at' => $eventData['ends_at'],
                ]
            );

            // Attach tags
            if (!empty($eventData['tags'])) {
                $tagIds = [];
                foreach ($eventData['tags'] as $tagSlug) {
                    $tag = $tags->get($tagSlug);
                    if ($tag) {
                        $tagIds[] = $tag->id;
                    }
                }
                $event->tags()->syncWithoutDetaching($tagIds);
            }

            // Create tickets
            if (!empty($eventData['tickets'])) {
                foreach ($eventData['tickets'] as $ticketData) {
                    $quantity = $ticketData['capacity'] ?? 100;
                    Ticket::firstOrCreate(
                        ['event_id' => $event->id, 'name' => $ticketData['name']],
                        [
                            'description' => $ticketData['description'] ?? null,
                            'price' => $ticketData['price'],
                            'currency' => 'KES',
                            'quantity' => $quantity,
                            'available' => $quantity,
                            'is_active' => true,
                        ]
                    );
                }
            }

            // Create speakers
            if (!empty($eventData['speakers'])) {
                foreach ($eventData['speakers'] as $speakerData) {
                    Speaker::firstOrCreate(
                        ['event_id' => $event->id, 'name' => $speakerData['name']],
                        [
                            'company' => $speakerData['company'] ?? null,
                            'designation' => $speakerData['designation'] ?? null,
                            'bio' => $speakerData['bio'] ?? null,
                            'is_featured' => $speakerData['is_featured'] ?? false,
                        ]
                    );
                }
            }
        }

        $this->command->info('Created ' . count($eventsData) . ' events with tickets and speakers.');
    }
}
