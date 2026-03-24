<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     * Schedules are dynamically loaded from the scheduler_settings table,
     * allowing administrators to configure run times and frequencies via API.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Load all enabled schedulers from database
        try {
            $schedulers = \App\Models\SchedulerSetting::enabled()->get();

            foreach ($schedulers as $scheduler) {
                $command = $schedule->command($scheduler->command_name);

                switch ($scheduler->frequency) {
                    case 'everyFifteenMinutes':
                        $command->everyFifteenMinutes();
                        break;
                    case 'everyThirtyMinutes':
                        $command->everyThirtyMinutes();
                        break;
                    case 'hourly':
                        $command->hourly();
                        break;
                    case 'everyFourHours':
                        $command->everyFourHours();
                        break;
                    case 'daily':
                        $command->dailyAt($scheduler->run_time);
                        break;
                    case 'weekly':
                        $command->weeklyOn(0, $scheduler->run_time); // Sunday
                        break;
                    case 'monthly':
                        $command->monthlyOn(1, $scheduler->run_time); // 1st of month
                        break;
                }

                $command->timezone('Africa/Nairobi')
                    ->name($scheduler->command_name)
                    ->withoutOverlapping();
            }
        } catch (\Exception $e) {
            // Fallback to hardcoded schedules if database is not available (e.g., during migrations)
            \Illuminate\Support\Facades\Log::warning('Scheduler settings table not available, using fallback', [
                'error' => $e->getMessage(),
            ]);

            // Fallback schedules (used when SchedulerSetting table unavailable)

            // Process queued jobs (Tier 2 notifications)
            $schedule->command('queue:work --stop-when-empty --max-time=25')
                ->everyThirtyMinutes()
                ->withoutOverlapping()
                ->runInBackground()
                ->name('queue.work');

            // Send reminders for pending donations (Daily)
            $schedule->command('donations:send-reminders --hours=24')
                ->dailyAt('09:00')
                ->timezone('Africa/Nairobi')
                ->name('donations.send_reminders');

            // Send subscription renewal reminders (Daily)
            $schedule->command('subscriptions:send-reminders')
                ->dailyAt('08:00')
                ->timezone('Africa/Nairobi')
                ->name('subscriptions.send_reminders');

            // ============================================================================
            // DESTRUCTIVE COMMANDS (Data Deletion & Cleanup)
            // ============================================================================

            // Clean webhook event records (Weekly on Sunday at 3 AM)
            $schedule->command('webhooks:cleanup-events')
                ->weeklyOn(0, '03:00')
                ->timezone('Africa/Nairobi')
                ->name('webhooks.cleanup_events');

            // Mark pending payments as expired (Hourly)
            $schedule->command('payments:cleanup-pending')
                ->hourly()
                ->timezone('Africa/Nairobi')
                ->name('payments.cleanup_pending');

            // Cleanup expired user data based on retention settings (Weekly on Sunday at 4 AM)
            $schedule->command('users:cleanup-expired --sync')
                ->weeklyOn(0, '04:00')
                ->timezone('Africa/Nairobi')
                ->name('users.cleanup_expired');

            // Cleanup temporary media uploads and stale chunks (Every 4 hours at 00:00, 04:00, 08:00, 12:00, 16:00, 20:00)
            $schedule->command('media:cleanup-temporary')
                ->everyFourHours()
                ->timezone('Africa/Nairobi')
                ->name('media.cleanup_temporary');

            // Delete old failed job records (Daily at 1 AM)
            $schedule->command('failed-jobs:cleanup')
                ->dailyAt('01:00')
                ->timezone('Africa/Nairobi')
                ->name('failed_jobs.cleanup');

            // Delete old audit log records (Monthly on 1st at 2 AM)
            $schedule->command('audit:cleanup-old')
                ->monthlyOn(1, '02:00')
                ->timezone('Africa/Nairobi')
                ->name('audit.cleanup_old');

            // Payment reconciliation
            $schedule->command('reconciliation:run')
                ->dailyAt('02:00')
                ->timezone('Africa/Nairobi')
                ->name('reconciliation.run');

            // Lab booking reminders (1 day before session)
            $schedule->command('lab:send-booking-reminders')
                ->dailyAt('21:30')
                ->timezone('Africa/Nairobi')
                ->name('lab.send_booking_reminders');

            // Event reminders (24h and 1h before event)
            $schedule->command('events:send-reminders')
                ->hourly()
                ->timezone('Africa/Nairobi')
                ->name('events.send_reminders');


            // Automatic marking of no-shows (Every 15 minutes)
            $schedule->command('lab:mark-no-shows')
                ->everyFifteenMinutes()
                ->timezone('Africa/Nairobi')
                ->name('lab.mark_no_shows');

            // Process expired subscriptions - enter grace period or downgrade (Weekly on Sunday at 5 AM)
            $schedule->command('subscriptions:process-expired')
                ->weeklyOn(0, '05:00')
                ->timezone('Africa/Nairobi')
                ->name('subscriptions.process_expired');

            // ============================================================================
            // MEDIUM PRIORITY COMMANDS (Process Status, Automate Workflows)
            // ============================================================================

            // Refresh exchange rates daily if cache expired (Daily at 1 AM)
            $schedule->command('exchange-rates:auto-refresh')
                ->dailyAt('01:00')
                ->timezone('Africa/Nairobi')
                ->name('exchange_rates.auto_refresh');

            // Process event waitlists and promote users when capacity opens (Hourly)
            $schedule->command('app:process-event-waitlist')
                ->hourly()
                ->timezone('Africa/Nairobi')
                ->name('events.process_waitlist');

            // Mark lab attendance after 15-minute grace period (Every 15 minutes)
            $schedule->command('lab:mark-attendance')
                ->everyFifteenMinutes()
                ->timezone('Africa/Nairobi')
                ->name('lab.mark_attendance');

            // PHASE 2: Monthly quota replenishment for lab subscriptions (Daily at 00:15)
            $schedule->command('quota:replenish-monthly')
                ->dailyAt('00:15')
                ->timezone('Africa/Nairobi')
                ->name('quota.replenish_monthly');

            // Note: Subscription renewals are handled by Pesapal recurring billing,
            // not by scheduled commands.
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
