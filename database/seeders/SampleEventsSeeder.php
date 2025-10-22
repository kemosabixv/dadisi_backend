<?php

namespace Database\Seeders;

use App\Models\Event;
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

        Event::firstOrCreate([
            'title' => 'Community Meet & Greet',
        ], [
            'slug' => Str::slug('Community Meet & Greet'),
            'description' => 'A community meetup for members and volunteers.',
            'venue' => 'Community Hall',
            'start_at' => now()->addWeeks(2),
            'end_at' => now()->addWeeks(2)->addHours(3),
            'county' => 'Nairobi',
            'capacity' => 100,
        ]);
    }
}
