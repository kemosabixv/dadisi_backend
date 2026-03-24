<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\MemberProfile;
use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SystemAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates a single, initial Super Admin account for production-like environments.
     */
    public function run(): void
    {
        $email = env('INITIAL_ADMIN_EMAIL', 'superadmin@dadisilab.com');
        $password = env('INITIAL_ADMIN_PASSWORD', 'password');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'username' => 'superadmin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        // Assign super_admin role
        $user->assignRole('super_admin');

        // Create initial profile
        $county = \App\Models\County::first();
        $plan = Plan::where('slug', 'community')->first();

        MemberProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'phone_number' => '+254700000000',
                'county_id' => $county?->id,
                'sub_county' => 'System',
                'ward' => 'System',
                'gender' => 'other',
                'date_of_birth' => '1990-01-01',
                'occupation' => 'System Admin',
                'bio' => 'Initial system administrator account.',
                'interests' => ['system'],
                'plan_id' => $plan?->id,
                'terms_accepted' => true,
                'is_staff' => true,
            ]
        );

        $this->command->info('Minimal System Admin created successfully!');
    }
}
