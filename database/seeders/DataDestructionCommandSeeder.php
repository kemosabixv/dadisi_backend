<?php

namespace Database\Seeders;

use App\Models\DataDestructionCommand;
use Illuminate\Database\Seeder;

class DataDestructionCommandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $commands = [
            // WEBHOOK CLEANUP
            [
                'command_name' => 'webhooks:cleanup-events',
                'job_class' => 'App\Jobs\CleanupWebhookEventsJob',
                'data_type' => 'webhook_events',
                'description' => 'Delete old webhook events from payment gateways',
                'notes' => 'Removes webhook events per retention policy. Runs weekly. Safe to run multiple times.',
                'is_enabled' => true,
                'supports_dry_run' => true,
                'supports_sync' => true,
                'is_critical' => false,
                'frequency' => 'weekly',
            ],

            // PAYMENT CLEANUP
            [
                'command_name' => 'payments:cleanup-pending',
                'job_class' => 'App\Jobs\CleanupPendingPaymentsJob',
                'data_type' => 'pending_payments',
                'description' => 'Expire and mark stale pending payment sessions',
                'notes' => 'Marks incomplete payments as expired. Runs hourly. CRITICAL for financial accuracy.',
                'is_enabled' => true,
                'supports_dry_run' => true,
                'supports_sync' => true,
                'is_critical' => true,
                'frequency' => 'hourly',
            ],

            // USER DATA CLEANUP
            [
                'command_name' => 'users:cleanup-expired',
                'job_class' => 'App\Jobs\CleanupExpiredUserData',
                'data_type' => 'generic',
                'description' => 'Clean up expired user data based on retention settings',
                'notes' => 'Handles multiple data types (sessions, audit logs, etc). Runs weekly. Respects soft/hard delete settings.',
                'is_enabled' => true,
                'supports_dry_run' => true,
                'supports_sync' => true,
                'is_critical' => false,
                'frequency' => 'weekly',
            ],

            // MEDIA CLEANUP
            [
                'command_name' => 'media:cleanup-temporary',
                'job_class' => 'App\Jobs\CleanupTemporaryMediaJob',
                'data_type' => 'temporary_media',
                'description' => 'Delete expired temporary media files and chunks',
                'notes' => 'Removes incomplete uploads and draft media. Runs every 4 hours. Manages disk space.',
                'is_enabled' => true,
                'supports_dry_run' => true,
                'supports_sync' => true,
                'is_critical' => false,
                'frequency' => 'everyFourHours',
            ],

            // FAILED JOBS CLEANUP
            [
                'command_name' => 'failed-jobs:cleanup',
                'job_class' => 'App\Jobs\CleanupFailedJobsJob',
                'data_type' => 'failed_jobs',
                'description' => 'Delete failed job records per retention policy',
                'notes' => 'Cleans failed_jobs table. Useful after resolving issues. Runs daily.',
                'is_enabled' => true,
                'supports_dry_run' => true,
                'supports_sync' => true,
                'is_critical' => false,
                'frequency' => 'daily',
            ],

            // AUDIT LOG CLEANUP
            [
                'command_name' => 'audit:cleanup-old',
                'job_class' => 'App\Jobs\CleanupAuditLogsJob',
                'data_type' => 'audit_logs',
                'description' => 'Delete old audit log records per retention policy',
                'notes' => 'Removes audit logs after retention period. Runs monthly. Important for GDPR/DPA compliance.',
                'is_enabled' => true,
                'supports_dry_run' => true,
                'supports_sync' => true,
                'is_critical' => false,
                'frequency' => 'monthly',
            ],
        ];

        foreach ($commands as $command) {
            DataDestructionCommand::updateOrCreate(
                ['command_name' => $command['command_name']],
                $command
            );
        }

        $this->command->info('✅ Data destruction commands seeded successfully!');
        $this->command->line('Created ' . count($commands) . ' data destruction commands');
    }
}
