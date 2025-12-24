<?php

namespace Database\Seeders;

use App\Models\UserDataRetentionSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserDataRetentionSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $retentionSettings = [
            [
                'data_type' => 'user_accounts',
                'retention_days' => 90,
                'auto_delete' => true,
                'description' => 'Soft-deleted user accounts and associated profiles',
            ],
            [
                'data_type' => 'audit_logs',
                'retention_days' => 365,
                'auto_delete' => true,
                'description' => 'User activity audit logs and system events',
            ],
            [
                'data_type' => 'backups',
                'retention_days' => 730,
                'auto_delete' => true,
                'description' => 'System backups and data exports',
            ],
            [
                'data_type' => 'temp_files',
                'retention_days' => 7,
                'auto_delete' => true,
                'description' => 'Temporary files and cached data',
            ],
            [
                'data_type' => 'session_data',
                'retention_days' => 30,
                'auto_delete' => true,
                'description' => 'User session data and tokens',
            ],
            [
                'data_type' => 'failed_jobs',
                'retention_days' => 60,
                'auto_delete' => true,
                'description' => 'Failed job records and error logs',
            ],
            [
                'data_type' => 'temporary_media',
                'retention_days' => 0,
                'retention_minutes' => 30,
                'auto_delete' => true,
                'description' => 'Temporary media uploads not attached to saved posts',
            ],
            [
                'data_type' => 'pending_payments',
                'retention_days' => 1,
                'retention_minutes' => 1440,
                'auto_delete' => true,
                'description' => 'Incomplete payment sessions and abandoned checkouts',
            ],
            [
                'data_type' => 'webhook_events',
                'retention_days' => 30,
                'auto_delete' => true,
                'description' => 'Incoming webhook notifications from payment providers',
            ],
        ];

        foreach ($retentionSettings as $setting) {
            UserDataRetentionSetting::firstOrCreate(
                ['data_type' => $setting['data_type']],
                $setting
            );
        }

        $this->command->info('Data retention settings seeded successfully!');
        $this->command->info('Available data types: ' . implode(', ', array_column($retentionSettings, 'data_type')));
    }
}
