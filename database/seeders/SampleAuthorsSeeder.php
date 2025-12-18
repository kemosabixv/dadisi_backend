<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class SampleAuthorsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure permissions cache is cleared
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure the role exists
        $authorRole = Role::firstWhere('name', 'author');

        // Create a demo author user if none exists
        $desiredUsername = Str::slug('Demo Author');
        $uniqueUsername = $desiredUsername;
        $counter = 1;
        while (User::where('username', $uniqueUsername)->exists()) {
            $uniqueUsername = $desiredUsername . '-' . $counter;
            $counter++;
        }

        $demo = User::firstOrCreate(
            ['email' => 'author@example.com'],
            [
                'username' => $uniqueUsername,
                'password' => bcrypt('password'),
            ]
        );

        if ($authorRole) {
            $demo->assignRole($authorRole);
        }

        // Assign 'author' role to existing premium/subscribed users if any
        // This attempts common column names - it's resilient if columns don't exist.
        $query = User::query();

        // If users table has subscription_plan_id, use it
        try {
            if (\Schema::hasColumn('users', 'subscription_plan_id')) {
                $query->orWhereNotNull('subscription_plan_id');
            }
        } catch (\Exception $e) {
            // Ignore if schema check fails in some environments
        }

        // If there is a 'is_premium' column
        try {
            if (\Schema::hasColumn('users', 'is_premium')) {
                $query->orWhere('is_premium', true);
            }
        } catch (\Exception $e) {
        }

        $users = $query->limit(50)->get();

        foreach ($users as $user) {
            $user->assignRole('author');
        }

        $this->command->info('Sample authors seeder completed. Demo author: author@example.com (password: password)');
    }
}
