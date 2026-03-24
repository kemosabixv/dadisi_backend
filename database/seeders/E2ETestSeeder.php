<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\MemberProfile;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\SubscriptionEnhancement;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class E2ETestSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure dependency data exists
        if (County::count() === 0) {
            $this->call(CountiesTableSeeder::class);
        }
        if (Plan::count() === 0) {
            $this->call(PlanSeeder::class);
        }
        if (\Spatie\Permission\Models\Role::count() === 0 || !\Spatie\Permission\Models\Role::where('name', 'super_admin')->exists()) {
            $this->call(RolesPermissionsSeeder::class);
        }

        $nairobiCounty = County::where('name', 'Nairobi')->first() ?? County::first();

        // Use existing plans from PlanSeeder
        $communityPlan = Plan::where('slug', 'community')->first();
        $premiumPlan = Plan::where('slug', 'premium')->first();

        // 1. Staff
        $this->createUser('superadmin@dadisilab.com', 'superadmin', 'Super Admin', 'super_admin');

        // 2. Premium Member
        $this->createUser('pascalmuchiri@gmail.com', 'pascalpremium', 'Pascal Premium', 'member', $premiumPlan);

        // 3. Free Member
        $this->createUser('kemosabixv@gmail.com', 'kemosabifree', 'Kemo Sabi', 'member', $communityPlan);

        $now = Carbon::now();
        $category = EventCategory::first();

        if (! $category) {
            $this->call(EventManagementSeeder::class);
            $category = EventCategory::first();
        }

        // 5. Waitlist Test Event (Free) - Capacity 1, Waitlist Enabled
        $freeEvent = Event::firstOrCreate(
            ['slug' => 'e2e-free-lab-session'],
            [
                'title' => 'E2E Free Lab Session (Waitlist)',
                'description' => 'A free session for E2E testing waitlist priority.',
                'category_id' => $category->id,
                'organizer_id' => User::where('email', 'superadmin@dadisilab.com')->first()->id,
                'venue' => 'Dadisi Main Lab',
                'google_maps_url' => 'https://maps.app.goo.gl/Yy9X9X9X9X9X9X9X9',
                'county_id' => $nairobiCounty->id,
                'capacity' => 1,
                'waitlist_enabled' => true,
                'price' => 0,
                'status' => 'published',
                'starts_at' => $now->copy()->addDays(5)->setTime(10, 0),
                'ends_at' => $now->copy()->addDays(5)->setTime(12, 0),
            ]
        );

        Ticket::updateOrCreate(
            ['event_id' => $freeEvent->id, 'name' => 'General Admission'],
            [
                'quantity' => 1,
                'available' => 1,
                'price' => 0,
                'is_active' => true,
            ]
        );

        // 6. Waitlist Test Event (Paid) - Capacity 1, Waitlist Enabled
        $paidEvent = Event::firstOrCreate(
            ['slug' => 'e2e-paid-biotech-workshop'],
            [
                'title' => 'E2E Paid Biotech Workshop (Waitlist)',
                'description' => 'A paid workshop for E2E testing guest flows with waitlist.',
                'category_id' => $category->id,
                'organizer_id' => User::where('email', 'superadmin@dadisilab.com')->first()->id,
                'venue' => 'Dadisi Wet Lab',
                'google_maps_url' => 'https://maps.app.goo.gl/Zz8Z8Z8Z8Z8Z8Z8Z8',
                'county_id' => $nairobiCounty->id,
                'capacity' => 1,
                'waitlist_enabled' => true,
                'price' => 1000,
                'status' => 'published',
                'starts_at' => $now->copy()->addDays(7)->setTime(14, 0),
                'ends_at' => $now->copy()->addDays(7)->setTime(17, 0),
            ]
        );

        Ticket::updateOrCreate(
            ['event_id' => $paidEvent->id, 'name' => 'Workshop Pass'],
            [
                'quantity' => 1,
                'available' => 1,
                'price' => 1000,
                'is_active' => true,
            ]
        );

        // 7. No Waitlist Test Event - Capacity 1, Waitlist Disabled
        $noWaitlistEvent = Event::firstOrCreate(
            ['slug' => 'e2e-no-waitlist-test'],
            [
                'title' => 'No Waitlist Event',
                'description' => 'Testing registration block when capacity is reached and waitlist is disabled.',
                'category_id' => $category->id,
                'organizer_id' => User::where('email', 'superadmin@dadisilab.com')->first()->id,
                'venue' => 'Conference Room A',
                'county_id' => $nairobiCounty->id,
                'capacity' => 1,
                'waitlist_enabled' => false,
                'price' => 0,
                'status' => 'published',
                'starts_at' => $now->copy()->addDays(8)->setTime(9, 0),
                'ends_at' => $now->copy()->addDays(8)->setTime(11, 0),
            ]
        );

        Ticket::updateOrCreate(
            ['event_id' => $noWaitlistEvent->id, 'name' => 'General Admission'],
            [
                'quantity' => 1,
                'available' => 1,
                'price' => 0,
                'is_active' => true,
            ]
        );

        // 8. Tiered Capacity Test - Multiple Tickets, Waitlist Enabled
        $tieredEvent = Event::firstOrCreate(
            ['slug' => 'e2e-tiered-capacity-test'],
            [
                'title' => 'E2E Tiered Capacity Workshop',
                'description' => 'Test event with multiple ticket tiers and waitlisting.',
                'category_id' => $category->id,
                'organizer_id' => User::where('email', 'superadmin@dadisilab.com')->first()->id,
                'venue' => 'Virtual Lab',
                'is_online' => true,
                'county_id' => $nairobiCounty->id,
                'capacity' => 10,
                'waitlist_enabled' => true,
                'price' => 500,
                'status' => 'published',
                'starts_at' => $now->copy()->addDays(10)->setTime(15, 0),
                'ends_at' => $now->copy()->addDays(10)->setTime(17, 0),
            ]
        );

        Ticket::updateOrCreate(
            ['event_id' => $tieredEvent->id, 'name' => 'Early Bird'],
            [
                'quantity' => 2,
                'available' => 2,
                'price' => 500,
                'is_active' => true,
            ]
        );

        Ticket::updateOrCreate(
            ['event_id' => $tieredEvent->id, 'name' => 'Regular Admission'],
            [
                'quantity' => 8,
                'available' => 8,
                'price' => 1000,
                'is_active' => true,
            ]
        );

        // 9. Promo Codes for Testing
        \App\Models\PromoCode::updateOrCreate(
            ['code' => 'E2E50', 'event_id' => $paidEvent->id],
            [
                'discount_type' => 'percentage',
                'discount_value' => 50,
                'is_active' => true,
            ]
        );

        \App\Models\PromoCode::updateOrCreate(
            ['code' => 'SAVE100', 'event_id' => $paidEvent->id],
            [
                'discount_type' => 'fixed',
                'discount_value' => 100,
                'is_active' => true,
            ]
        );

        // =============================================
        // Lab Booking E2E Test Data
        // =============================================

        $premiumUser = User::where('email', 'pascalmuchiri@gmail.com')->first();
        $freeUser = User::where('email', 'kemosabixv@gmail.com')->first();
        $superAdmin = User::where('email', 'superadmin@dadisilab.com')->first();

        $dryLab = \App\Models\LabSpace::where('slug', 'dry-lab')->first();
        $greenhouse = \App\Models\LabSpace::where('slug', 'greenhouse')->first();

        if ($premiumUser && $dryLab) {
            // Confirmed booking for Premium user (tomorrow 10:00–14:00)
            \App\Models\LabBooking::updateOrCreate(
                [
                    'user_id' => $premiumUser->id,
                    'lab_space_id' => $dryLab->id,
                    'starts_at' => $now->copy()->addDay()->setTime(10, 0),
                ],
                [
                    'ends_at' => $now->copy()->addDay()->setTime(14, 0),
                    'purpose' => 'E2E Test: Bioinformatics data analysis session',
                    'title' => 'E2E Premium Booking',
                    'slot_type' => 'half_day',
                    'status' => 'confirmed',
                    'quota_consumed' => true,
                ]
            );
            $this->command->info('  → Created E2E lab booking for Premium user (Dry Lab)');
        }

        if ($greenhouse && $superAdmin) {
            // Near-capacity scenario: 3 bookings on Greenhouse (capacity 4) → 1 slot left
            for ($i = 1; $i <= 3; $i++) {
                $bookingUser = User::firstOrCreate(
                    ['email' => "e2e-greenhouse-{$i}@test.com"],
                    [
                        'username' => "e2e-greenhouse-user-{$i}",
                        'password' => \Illuminate\Support\Facades\Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );

                \App\Models\LabBooking::updateOrCreate(
                    [
                        'user_id' => $bookingUser->id,
                        'lab_space_id' => $greenhouse->id,
                        'starts_at' => $now->copy()->addDays(3)->setTime(9, 0),
                    ],
                    [
                        'ends_at' => $now->copy()->addDays(3)->setTime(10, 0),
                        'purpose' => "E2E Test: Greenhouse near-capacity booking #{$i}",
                        'title' => "E2E Greenhouse Booking #{$i}",
                        'slot_type' => 'hourly',
                        'status' => 'confirmed',
                        'quota_consumed' => false,
                    ]
                );
            }
            $this->command->info('  → Created 3 near-capacity bookings on Greenhouse (1 slot remaining)');
        }

        if ($freeUser && $dryLab) {
            // Booking within cancellation deadline (<1 day away) for deadline testing
            \App\Models\LabBooking::updateOrCreate(
                [
                    'user_id' => $freeUser->id,
                    'lab_space_id' => $dryLab->id,
                    'starts_at' => $now->copy()->addHours(12)->setMinute(0)->setSecond(0),
                ],
                [
                    'ends_at' => $now->copy()->addHours(13)->setMinute(0)->setSecond(0),
                    'purpose' => 'E2E Test: Booking within cancellation deadline',
                    'title' => 'E2E Deadline Test',
                    'slot_type' => 'hourly',
                    'status' => 'confirmed',
                    'quota_consumed' => false,
                ]
            );
            $this->command->info('  → Created deadline-window booking for Free user (Dry Lab)');
        }

        $this->command->info('E2E Test data seeded successfully!');
    }

    private function createUser($email, $username, $name, $role, $plan = null)
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'username' => $username,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (! $user->wasRecentlyCreated) {
            $user->update(['password' => Hash::make('password')]);
        }

        // Add roles if spatie/laravel-permission is installed
        if (method_exists($user, 'syncRoles')) {
            $user->syncRoles([$role]);
        }

        if (! $user->memberProfile) {
            $nameParts = explode(' ', $name, 2);
            MemberProfile::create([
                'user_id' => $user->id,
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? '',
                'phone_number' => '+2547'.rand(10000000, 99999999),
                'county_id' => rand(1, 47),
                'terms_accepted' => true,
                'is_staff' => $role !== 'member',
                'plan_id' => $plan?->id,
            ]);
        } elseif ($plan) {
            $user->memberProfile->update(['plan_id' => $plan->id]);
        }

        if ($plan) {
            $subscription = PlanSubscription::where('subscriber_id', $user->id)
                ->where('subscriber_type', $user->getMorphClass())
                ->where('plan_id', $plan->id)
                ->first();

            if (! $subscription) {
                $subscription = PlanSubscription::create([
                    'subscriber_id' => $user->id,
                    'subscriber_type' => $user->getMorphClass(),
                    'plan_id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug.'-'.Str::random(5),
                    'starts_at' => now(),
                    'ends_at' => now()->addYear(),
                ]);
            }

            SubscriptionEnhancement::updateOrCreate(
                ['subscription_id' => $subscription->id],
                ['status' => 'active']
            );

            $user->update([
                'active_subscription_id' => $subscription->id,
                'subscription_status' => 'active',
                'subscription_activated_at' => now(),
            ]);
        }

        return $user;
    }
}
