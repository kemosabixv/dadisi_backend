<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EventCategory;
use App\Models\EventTag;
use App\Models\EscrowConfiguration;
use Illuminate\Support\Str;

class EventManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Categories with colors for calendar display
        $categories = [
            ['name' => 'Biotech & Health', 'icon' => 'microscope', 'color' => '#0284C7'],      // sky-600
            ['name' => 'Community Science', 'icon' => 'users', 'color' => '#4F46E5'],         // indigo-600
            ['name' => 'Education & Tutorials', 'icon' => 'graduation-cap', 'color' => '#059669'], // emerald-600
            ['name' => 'Environmental Science', 'icon' => 'leaf', 'color' => '#0D9488'],       // teal-600
            ['name' => 'Technology & Coding', 'icon' => 'code', 'color' => '#7C3AED'],         // violet-600
            ['name' => 'Workshops & Hands-on', 'icon' => 'wrench', 'color' => '#D97706'],      // amber-600
        ];

        foreach ($categories as $cat) {
            EventCategory::firstOrCreate(
                ['slug' => Str::slug($cat['name'])],
                [
                    'name' => $cat['name'],
                    'description' => $cat['name'] . ' events and sessions.',
                    'color' => $cat['color'],
                    'is_active' => true,
                    'sort_order' => 0
                ]
            );
        }

        // 2. Seed Tags
        $tags = ['Online', 'In-person', 'Beginner', 'Advanced', 'Free', 'Paid', 'Hybrid', 'Workshop', 'Conference'];

        foreach ($tags as $tagName) {
            EventTag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName]
            );
        }

        // 3. Seed Default Escrow Configurations
        // Default rule: Hold 3 days, 0% immediate release
        EscrowConfiguration::firstOrCreate(
            ['event_type' => null, 'organizer_trust_level' => null],
            [
                'min_ticket_price' => 0,
                'max_ticket_price' => null,
                'hold_days_after_event' => 3,
                'release_percentage_immediate' => 0,
                'is_active' => true,
            ]
        );

        // Immediate release (100%) for very low price tickets (< 100 KES)
        EscrowConfiguration::firstOrCreate(
            ['event_type' => null, 'organizer_trust_level' => null, 'max_ticket_price' => 100],
            [
                'min_ticket_price' => 0,
                'hold_days_after_event' => 0,
                'release_percentage_immediate' => 100,
                'is_active' => true,
            ]
        );

        // Featured organizers (trusted) get 50% immediate release
        EscrowConfiguration::firstOrCreate(
            ['event_type' => null, 'organizer_trust_level' => 'trusted'],
            [
                'min_ticket_price' => 0,
                'max_ticket_price' => null,
                'hold_days_after_event' => 2,
                'release_percentage_immediate' => 50,
                'is_active' => true,
            ]
        );
    }
}
