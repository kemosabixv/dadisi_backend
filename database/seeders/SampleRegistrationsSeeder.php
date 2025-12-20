<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Registration;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SampleRegistrationsSeeder extends Seeder
{
    public function run(): void
    {
        $events = Event::with('tickets')->where('status', 'published')->get();
        
        if ($events->isEmpty()) {
            $this->command->warn('No published events found. Skipping registrations seeding.');
            return;
        }

        // Get or create sample users for registrations
        $users = $this->getOrCreateSampleUsers(20);
        
        $totalRegistrations = 0;

        foreach ($events as $event) {
            $tickets = $event->tickets;
            
            if ($tickets->isEmpty()) {
                continue;
            }

            // Determine how many registrations for this event (5-15)
            $registrationCount = rand(5, min(15, $event->capacity ?? 50));
            $isPastEvent = Carbon::parse($event->starts_at)->isPast();

            for ($i = 0; $i < $registrationCount; $i++) {
                $user = $users->random();
                $ticket = $tickets->random();

                // Avoid duplicate registrations for same user/event
                $exists = Registration::where('event_id', $event->id)
                    ->where('user_id', $user->id)
                    ->exists();
                
                if ($exists) {
                    continue;
                }

                // Determine status
                $statusOptions = ['confirmed', 'confirmed', 'confirmed', 'waitlisted', 'cancelled'];
                $status = $statusOptions[array_rand($statusOptions)];
                
                // If past event, most should be confirmed or attended
                if ($isPastEvent) {
                    $status = rand(0, 10) > 1 ? 'attended' : 'confirmed';
                }

                $registration = Registration::create([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'ticket_id' => $ticket->id,
                    'confirmation_code' => strtoupper(Str::random(8)),
                    'status' => $status,
                    'qr_code_token' => Str::uuid()->toString(),
                    'check_in_at' => ($status === 'attended' && $isPastEvent) ? $event->starts_at->addMinutes(rand(0, 60)) : null,
                    'waitlist_position' => $status === 'waitlisted' ? rand(1, 10) : null,
                ]);

                $totalRegistrations++;
            }
        }

        $this->command->info("Created {$totalRegistrations} registrations across " . $events->count() . " events.");
    }

    private function getOrCreateSampleUsers(int $count): \Illuminate\Support\Collection
    {
        // Try to get existing users first
        $existingUsers = User::whereDoesntHave('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->inRandomOrder()->take($count)->get();

        if ($existingUsers->count() >= $count) {
            return $existingUsers;
        }

        // Create additional sample users if needed
        $needed = $count - $existingUsers->count();
        $newUsers = collect();

        $firstNames = ['John', 'Jane', 'Alice', 'Bob', 'Carol', 'David', 'Eve', 'Frank', 'Grace', 'Henry', 'Ivy', 'Jack', 'Kate', 'Leo', 'Mia', 'Noah', 'Olivia', 'Peter', 'Quinn', 'Rose'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin'];

        for ($i = 0; $i < $needed; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $email = strtolower($firstName . '.' . $lastName . rand(100, 999) . '@example.com');

            // Skip if email exists
            if (User::where('email', $email)->exists()) {
                continue;
            }

            $user = User::create([
                'username' => strtolower($firstName . $lastName . rand(100, 999)),
                'email' => $email,
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);

            $newUsers->push($user);
        }

        return $existingUsers->merge($newUsers);
    }
}
