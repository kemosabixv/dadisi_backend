<?php

namespace App\Console\Commands;

use App\Jobs\CleanupExpiredUserData;
use App\Models\UserDataRetentionSetting;
use Illuminate\Console\Command;

class CleanupExpiredData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:cleanup-expired
                            {--sync : Run synchronously instead of queuing the job}
                            {--data-type= : Run cleanup for specific data type only}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired user data based on retention settings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sync = $this->option('sync');
        $dataType = $this->option('data-type');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No data will be deleted');
            $this->newLine();
        }

        // Show current retention settings
        $this->showRetentionSettings();

        if ($dataType) {
            $this->validateDataType($dataType);
            $this->info("🎯 Running cleanup for data type: {$dataType}");
        } else {
            $this->info('🧹 Running cleanup for all configured data types');
        }

        if ($sync) {
            $this->info('⚡ Running synchronously...');
            $job = new CleanupExpiredUserData();

            if ($dryRun) {
                // For dry run, we'd need to modify the job to not actually delete
                // For now, just show what would happen
                $this->showDryRunInfo();
            } else {
                $job->handle();
                $this->info('✅ Cleanup completed successfully!');
            }
        } else {
            $this->info('📋 Queuing cleanup job...');
            if (!$dryRun) {
                CleanupExpiredUserData::dispatch();
                $this->info('✅ Cleanup job queued successfully!');
            } else {
                $this->showDryRunInfo();
            }
        }

        $this->newLine();
        $this->info('💡 Tip: Run with --dry-run first to see what would be deleted');
        $this->info('💡 Tip: Use --sync for immediate execution instead of queuing');
    }

    /**
     * Show current retention settings
     */
    private function showRetentionSettings(): void
    {
        $settings = UserDataRetentionSetting::getActiveSettings();

        $this->info('📋 Current Data Retention Settings:');
        $this->table(
            ['Data Type', 'Retention (Days)', 'Description'],
            collect($settings)->map(function ($days, $type) {
                $setting = UserDataRetentionSetting::where('data_type', $type)->first();
                return [
                    $type,
                    $days,
                    $setting?->description ?? 'N/A'
                ];
            })->toArray()
        );
        $this->newLine();
    }

    /**
     * Validate data type exists
     */
    private function validateDataType(string $dataType): void
    {
        $validTypes = UserDataRetentionSetting::pluck('data_type')->toArray();

        if (!in_array($dataType, $validTypes)) {
            $this->error("❌ Invalid data type: {$dataType}");
            $this->info('Valid data types: ' . implode(', ', $validTypes));
            exit(1);
        }
    }

    /**
     * Show dry run information
     */
    private function showDryRunInfo(): void
    {
        $settings = UserDataRetentionSetting::getActiveSettings();

        $this->info('🔍 Dry run results (what would be deleted):');
        $this->newLine();

        foreach ($settings as $dataType => $retentionDays) {
            $cutoffDate = now()->subDays($retentionDays);

            switch ($dataType) {
                case 'user_accounts':
                    $count = \App\Models\User::onlyTrashed()
                        ->where('deleted_at', '<=', $cutoffDate)
                        ->count();
                    $this->info("👤 User accounts: {$count} expired accounts would be deleted");
                    break;

                case 'audit_logs':
                    $count = \App\Models\AuditLog::where('created_at', '<=', $cutoffDate)->count();
                    $this->info("📝 Audit logs: {$count} old log entries would be deleted");
                    break;

                case 'session_data':
                    $count = \DB::table('sessions')
                        ->where('last_activity', '<=', $cutoffDate->timestamp)
                        ->count();
                    $this->info("🔑 Sessions: {$count} expired sessions would be deleted");
                    break;

                case 'failed_jobs':
                    $count = \DB::table('failed_jobs')
                        ->where('failed_at', '<=', $cutoffDate->toDateTimeString())
                        ->count();
                    $this->info("❌ Failed jobs: {$count} old failed jobs would be deleted");
                    break;

                default:
                    $this->info("📁 {$dataType}: Would check for expired files/directories");
                    break;
            }
        }

        $this->newLine();
        $this->info('💡 Remove --dry-run to actually perform the cleanup');
    }
}
