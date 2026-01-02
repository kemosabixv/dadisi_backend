<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;

class GroupMembersSeeder extends Seeder
{
    /**
     * Seed group memberships - have users join their county groups.
     */
    public function run(): void
    {
        $users = User::with('memberProfile')->get();
        $groups = Group::with('county')->get();

        if ($users->isEmpty() || $groups->isEmpty()) {
            return;
        }

        foreach ($groups as $group) {
            // Get random 3-8 users to join each group
            $memberCount = rand(3, min(8, $users->count()));
            $selectedUsers = $users->random($memberCount);

            foreach ($selectedUsers as $user) {
                // Only add if not already a member
                if (!$group->members()->where('user_id', $user->id)->exists()) {
                    $group->members()->attach($user->id, [
                        'role' => 'member',
                        'joined_at' => now()->subDays(rand(1, 60)),
                    ]);
                }
            }

            // Update member_count on group
            $group->update(['member_count' => $group->members()->count()]);
        }

        $this->command->info('Group memberships seeded successfully.');
    }
}
