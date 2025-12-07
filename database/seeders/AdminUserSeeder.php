<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\MemberProfile;
use App\Models\Plan;
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
                'username' => 'adminuser',
                'display_name' => 'Admin User',
                'role' => 'admin',
            ],
            [
                'email' => 'finance@dadisilab.com',
                'username' => 'financeofficer',
                'display_name' => 'Finance Officer',
                'role' => 'finance',
            ],
            [
                'email' => 'events@dadisilab.com',
                'username' => 'eventsmanager',
                'display_name' => 'Events Manager',
                'role' => 'events_manager',
            ],
            [
                'email' => 'blog@dadisilab.com',
                'username' => 'contenteditor',
                'display_name' => 'Content Editor',
                'role' => 'content_editor',
            ],
            // Regular member users
            [
                'email' => 'john.doe@dadisilab.com',
                'username' => 'johndoe',
                'display_name' => 'John Doe',
                'role' => 'member',
                'subscription_plan' => 'Free',
            ],
            [
                'email' => 'jane.smith@dadisilab.com',
                'username' => 'janesmith',
                'display_name' => 'Jane Smith',
                'role' => 'member',
                'subscription_plan' => 'Premium',
            ],
            [
                'email' => 'student@dadisilab.com',
                'username' => 'alexstudent',
                'display_name' => 'Alex Student',
                'role' => 'member',
                'subscription_plan' => 'Student',
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

                MemberProfile::create([
                    'user_id' => $user->id,
                    'first_name' => $nameParts[0] ?? '',
                    'last_name' => $nameParts[1] ?? '',
                    'county_id' => 1, // Default to first county (Nairobi)
                    'plan_id' => $plan?->id,
                    'terms_accepted' => true,
                    'marketing_consent' => false,
                    'is_staff' => $userData['role'] !== 'member',
                ]);
            }

            $this->command->info("Demo user {$userData['email']} created/updated with {$userData['role']} role");
        }

        $this->command->info('Demo users seeded successfully!');
    }
}
