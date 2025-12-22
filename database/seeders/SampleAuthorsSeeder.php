<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates sample users for testing content creation.
 * Note: The 'author' role was removed - access to authoring is now
 * controlled via subscription features, not roles.
 */
class SampleAuthorsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a demo content creator user if none exists
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

        $this->command->info('Sample authors seeder completed. Demo user: author@example.com (password: password)');
        $this->command->info('Note: Author role was removed. Authoring access is controlled via subscriptions.');
    }
}
