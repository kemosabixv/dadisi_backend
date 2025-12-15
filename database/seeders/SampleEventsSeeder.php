<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\County;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SampleEventsSeeder extends Seeder
{
    public function run(): void
    {
        // If Event model doesn't exist yet this will fail; keep seeds minimal
        if (!class_exists(Event::class)) {
            return;
        }

        $county = County::where('name', 'Nairobi')->first() ?? County::first();

        Event::firstOrCreate([
            'title' => 'Community Meet & Greet',
        ], [
            'slug' => Str::slug('Community Meet & Greet'),
            'description' => 'A community meetup for members and volunteers.',
            'venue' => 'Community Hall',
            'starts_at' => now()->addWeeks(2),
            'ends_at' => now()->addWeeks(2)->addHours(3),
            'county_id' => $county?->id,
            'capacity' => 100,
        ]);
    }
}
