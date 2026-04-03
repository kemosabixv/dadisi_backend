<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PrivateGroupsSeeder extends Seeder
{
    /**
     * Seed private groups for RBAC testing.
     */
    public function run(): void
    {
        $admin = User::role('admin')->first();
        $staff = User::role('staff')->first();
        $members = User::role('member')->take(5)->get();

        if (!$admin) {
            return;
        }

        // 1. Create a Staff-Only Group
        $staffGroup = Group::create([
            'name' => 'Internal Staff Hub',
            'slug' => 'internal-staff-hub',
            'description' => 'Restricted discussion area for Dadisi staff members.',
            'is_private' => true,
            'is_active' => true,
        ]);

        $staffGroup->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);
        if ($staff) {
            $staffGroup->members()->attach($staff->id, ['role' => 'moderator', 'joined_at' => now()]);
        }

        // 2. Create a County Leadership Group (Private)
        $countyGroup = Group::create([
            'name' => 'Nairobi County Council',
            'slug' => 'nairobi-county-council',
            'description' => 'Private coordination group for Nairobi county leadership.',
            'is_private' => true,
            'is_active' => true,
            'county_id' => 1, // Nairobi
        ]);

        $countyGroup->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);
        foreach ($members as $member) {
            $countyGroup->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        }

        $this->command->info('Private groups seeded successfully.');
    }
}
