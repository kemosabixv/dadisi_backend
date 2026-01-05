<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\MemberProfile;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\SubscriptionEnhancement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Demo users for different roles - all with password: 'password'
        $demoUsers = [
            // Admin users
            [
                'email' => 'superadmin@dadisilab.com',
                'username' => 'superadmin',
                'display_name' => 'Super Admin',
                'role' => 'super_admin',
            ],
            [
                'email' => 'admin@dadisilab.com',
                'username' => 'admin',
                'display_name' => 'Admin User',
                'role' => 'admin',
            ],
            [
                'email' => 'moderator@dadisilab.com',
                'username' => 'moderator',
                'display_name' => 'Mod User',
                'role' => 'moderator',
            ],
            // Regular member users
            [
                'email' => 'john.doe@dadisilab.com',
                'username' => 'johndoe',
                'display_name' => 'John Doe',
                'role' => 'member',
                'subscription_plan' => 'Community', // Free tier
            ],
            [
                'email' => 'jane.smith@dadisilab.com',
                'username' => 'janesmith',
                'display_name' => 'Jane Smith',
                'role' => 'member',
                'subscription_plan' => 'Community', // Free tier for mocking new user
            ],
            [
                'email' => 'student@dadisilab.com',
                'username' => 'alexstudent',
                'display_name' => 'Alex Student',
                'role' => 'member',
                'subscription_plan' => 'Student/Researcher',
            ],
        ];

        foreach ($demoUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'username' => $userData['username'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            // Assign role
            $user->assignRole($userData['role']);

            // Create member profile if it doesn't exist
            if (!$user->memberProfile) {
                $nameParts = explode(' ', $userData['display_name'], 2);

                // Get the appropriate subscription plan
                $planName = $userData['subscription_plan'] ?? 'Free';
                $plan = Plan::where('slug', Str::slug($planName))->first();

                // Generate realistic demo data
                $genders = ['male', 'female'];
                $occupations = [
                    'Software Developer',
                    'Research Scientist',
                    'Community Organizer',
                    'Student',
                    'Lab Technician',
                    'Environmental Scientist',
                ];

                MemberProfile::create([
                    'user_id' => $user->id,
                    'first_name' => $nameParts[0] ?? '',
                    'last_name' => $nameParts[1] ?? '',
                    'phone_number' => '+2547' . rand(10000000, 99999999),
                    'county_id' => rand(1, 47), // Random county from Kenya's 47 counties
                    'sub_county' => 'Demo Sub-County',
                    'ward' => 'Demo Ward',
                    'gender' => $genders[array_rand($genders)],
                    'date_of_birth' => now()->subYears(rand(20, 45))->format('Y-m-d'),
                    'occupation' => $occupations[array_rand($occupations)],
                    'bio' => 'A passionate member of the Dadisi Community Labs community.',
                    'interests' => ['technology', 'community', 'biotech'],
                    'plan_id' => $plan?->id,
                    'terms_accepted' => true,
                    'marketing_consent' => (bool) rand(0, 1),
                    'is_staff' => $userData['role'] !== 'member',
                ]);
            }

            // Create actual PlanSubscription record for members with a plan
            if (isset($userData['subscription_plan'])) {
                $planName = $userData['subscription_plan'];
                $plan = Plan::where('slug', Str::slug($planName))->first();
                
                if ($plan) {
                    // Check if subscription already exists
                    $existingSubscription = PlanSubscription::where('subscriber_id', $user->id)
                        ->where('subscriber_type', 'App\Models\User')
                        ->where('plan_id', $plan->id)
                        ->first();
                    
                    if (!$existingSubscription) {
                        $subscription = PlanSubscription::create([
                            'subscriber_id' => $user->id,
                            'subscriber_type' => 'App\Models\User',
                            'plan_id' => $plan->id,
                            'name' => $plan->name,
                            'slug' => $plan->slug . '-' . $user->id . '-' . time(),
                            'starts_at' => now(),
                            'ends_at' => now()->addYear(), // 1 year subscription
                            'trial_ends_at' => null,
                        ]);

                        // Create subscription enhancement (active status)
                        SubscriptionEnhancement::create([
                            'subscription_id' => $subscription->id,
                            'status' => 'active',
                            'max_renewal_attempts' => 3,
                        ]);

                        // Set user's active subscription
                        $user->update([
                            'active_subscription_id' => $subscription->id,
                            'subscription_status' => 'active',
                            'subscription_activated_at' => now(),
                        ]);
                        
                        $this->command->info("  → Created subscription for {$userData['email']}: {$planName}");
                    }
                }
            }

            $this->command->info("Demo user {$userData['email']} created/updated with {$userData['role']} role");
        }

        $this->command->info('Demo users seeded successfully!');
    }
}
