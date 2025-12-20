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
                'command_name' => 'media:cleanup-temporary',
                'run_time' => '03:00',
                'frequency' => 'hourly',
                'enabled' => true,
                'description' => 'Cleanup temporary media uploads that expired (not attached to saved posts)',
            ],
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
            [
                'command_name' => 'renewals:send-reminders',
                'run_time' => '09:00',
                'frequency' => 'daily',
                'enabled' => true,
                'description' => 'Send renewal reminders to members with expiring subscriptions',
            ],
            [
                'command_name' => 'renewals:enqueue-due',
                'run_time' => '00:00',
                'frequency' => 'hourly',
                'enabled' => true,
                'description' => 'Enqueue auto-renewals that are due for processing',
            ],
            [
                'command_name' => 'reconciliation:run',
                'run_time' => '02:00',
                'frequency' => 'daily',
                'enabled' => true,
                'description' => 'Run payment reconciliation against external payment providers',
            ],
        ];

        foreach ($schedulers as $scheduler) {
            SchedulerSetting::firstOrCreate(
                ['command_name' => $scheduler['command_name']],
                $scheduler
            );
        }

        $this->command->info('Scheduler settings seeded successfully!');
        $this->command->info('Available commands: ' . implode(', ', array_column($schedulers, 'command_name')));
    }
}
