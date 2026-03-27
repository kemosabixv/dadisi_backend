<?php

namespace Database\Seeders;

use App\Models\UserDataRetentionSetting;
use Illuminate\Database\Seeder;

class UserDataRetentionSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'data_type' => 'audit_logs',
                'retention_days' => 365,  // 1 year
                'auto_delete' => true,
                'is_soft_delete' => false,
                'is_enabled' => true,
                'description' => 'User activity and system audit logs for compliance and debugging',
                'notes' => 'Kept for 1 year for compliance and debugging. Hard deleted after retention period.',
            ],
            [
                'data_type' => 'webhook_events',
                'retention_days' => 90,   // 3 months
                'auto_delete' => true,
                'is_soft_delete' => false,
                'is_enabled' => true,
                'description' => 'Payment gateway webhook events for reconciliation and debugging',
                'notes' => 'Keep recent events for debugging payment issues. Old ones safely cleaned up.',
            ],
            [
                'data_type' => 'pending_payments',
                'retention_days' => 30,   // 1 month
                'auto_delete' => true,
                'is_soft_delete' => false,
                'is_enabled' => true,
                'description' => 'Incomplete payment sessions and expired orders',
                'notes' => 'Stale/abandoned payments. Hard deleted after 30 days to keep database clean.',
            ],
            [
                'data_type' => 'failed_jobs',
                'retention_days' => 60,   // 2 months
                'auto_delete' => true,
                'is_soft_delete' => false,
                'is_enabled' => true,
                'description' => 'Failed queue job records for debugging',
                'notes' => 'Keep for 2 months for debugging job failures. Soft delete for recovery.',
            ],
            [
                'data_type' => 'user_sessions',
                'retention_days' => 7,    // 1 week
                'auto_delete' => true,
                'is_soft_delete' => false,
                'is_enabled' => true,
                'description' => 'Active user session records for security',
                'notes' => 'Short retention period for security. Automatically cleaned up.',
            ],
            [
                'data_type' => 'temporary_media',
                'retention_days' => 7,    // 1 week
                'auto_delete' => true,
                'is_soft_delete' => false,
                'is_enabled' => true,
                'description' => 'Temporary file uploads, media chunks, and draft files',
                'notes' => 'Cleanup runs every 4 hours. Hard deleted after 7 days to manage disk space.',
            ],
            [
                'data_type' => 'other',
                'retention_days' => 90,
                'auto_delete' => true,
                'is_soft_delete' => true,
                'is_enabled' => true,
                'description' => 'Default retention setting for miscellaneous data',
                'notes' => 'Default fallback for data types not explicitly configured.',
            ],
        ];

        foreach ($settings as $setting) {
            UserDataRetentionSetting::updateOrCreate(
                ['data_type' => $setting['data_type']],
                $setting
            );
        }

        $this->command->info('✅ Retention settings seeded successfully!');
    }
}
