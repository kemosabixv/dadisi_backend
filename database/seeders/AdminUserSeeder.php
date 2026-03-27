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
            [
                'email' => 'finance@dadisilab.com',
                'username' => 'finance',
                'display_name' => 'Finance User',
                'role' => 'finance_manager',
            ],
            [
                'email' => 'events@dadisilab.com',
                'username' => 'eventsmanager',
                'display_name' => 'Events Manager',
                'role' => 'event_manager',
            ],
            [
                'email' => 'blog@dadisilab.com',
                'username' => 'contenteditor',
                'display_name' => 'Content Editor',
                'role' => 'content_manager',
            ],
            // Lab Staff
            [
                'email' => 'supervisor@dadisilab.com',
                'username' => 'labsupervisor',
                'display_name' => 'Lab Supervisor',
                'role' => 'lab_supervisor',
            ],
            [
                'email' => 'manager@dadisilab.com',
                'username' => 'labmanager',
                'display_name' => 'Lab Manager',
                'role' => 'lab_manager',
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


        // Fetch all county IDs
        $countyIds = \App\Models\County::pluck('id')->toArray();

        foreach ($demoUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'username' => $userData['username'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            // Assign role - use syncRoles to enforce mutual exclusivity
            $user->syncRoles([$userData['role']]);

            // Create member profile if it doesn't exist
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

            $randomCountyId = $countyIds[array_rand($countyIds)];

            MemberProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name' => $nameParts[0] ?? '',
                    'last_name' => $nameParts[1] ?? '',
                    'phone_number' => '+2547' . rand(10000000, 99999999),
                    'county_id' => $randomCountyId,
                    'sub_county' => 'Demo Sub-County',
                    'ward' => 'Demo Ward',
                    'gender' => current($genders), // Just pick first to avoid changing on reseed
                    'date_of_birth' => now()->subYears(25)->format('Y-m-d'),
                    'occupation' => current($occupations),
                    'bio' => 'A passionate member of the Dadisi Community Labs community.',
                    'interests' => ['technology', 'community', 'biotech'],
                    'plan_id' => $plan?->id,
                    'terms_accepted' => true,
                    'marketing_consent' => (bool) rand(0, 1),
                ]
            );

            // Assign profile picture (CAS / R2)
            if (app()->environment('local', 'testing', 'staging')) {
                $avatarImages = [
                    'seed-images/supervisor.png',
                    'seed-images/manager.png',
                    'seed-images/john-doe.png',
                ];
                $randomAvatar = $avatarImages[array_rand($avatarImages)];
                $absolutePath = storage_path('app/public/' . $randomAvatar);
                
                if (file_exists($absolutePath)) {
                    try {
                        /** @var \App\Services\Media\MediaService $mediaService */
                        $mediaService = app(\App\Services\Media\MediaService::class);
                        $media = $mediaService->registerFile(
                            $user,
                            $absolutePath,
                            basename($randomAvatar),
                            [
                                'visibility' => 'public',
                                'root_type' => 'public',
                                'path' => ['profiles', $user->username],
                            ]
                        );
                        $user->update(['profile_picture_path' => $media->file_path]);
                    } catch (\Exception $e) {
                        $this->command->warn('Failed to register CAS avatar for user: ' . $user->username);
                    }
                }
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
