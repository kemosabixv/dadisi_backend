<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SchedulerSetting;

class SchedulerSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $schedulers = [
            [
                'command_name' => 'media:cleanup',
                'run_time' => '03:00',
                'frequency' => 'daily',
                'enabled' => true,
                'description' => 'Clean up orphaned media files',
            ],
            [
                'command_name' => 'users:cleanup-expired',
                'run_time' => '02:00',
                'frequency' => 'daily',
                'enabled' => true,
                'description' => 'Clean up expired user data based on retention settings',
            ],
        ];

        foreach ($schedulers as $scheduler) {
            SchedulerSetting::firstOrCreate(
                ['command_name' => $scheduler['command_name']],
                $scheduler
            );
        }

        $this->command->info('Scheduler settings seeded successfully!');
    }
}
