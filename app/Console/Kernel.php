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
                'error' => $e->getMessage()
            ]);
            
            // Fallback schedules (used when SchedulerSetting table unavailable)
            
            // Process queued jobs (Tier 2 notifications)
            $schedule->command('queue:work --stop-when-empty --max-time=25')
                ->everyThirtyMinutes()
                ->withoutOverlapping()
                ->runInBackground()
                ->name('queue.work');
            
            // Cleanup temporary media uploads and stale chunks (Every 4 hours)
            $schedule->command('media:cleanup-temporary')
                ->everyFourHours()
                ->timezone('Africa/Nairobi')
                ->name('media.cleanup_temporary');
            
            // Cleanup expired user data based on retention settings
            $schedule->command('users:cleanup-expired --sync')
                ->weeklyOn(0, '04:00') // Sunday at 4am
                ->timezone('Africa/Nairobi')
                ->name('users.cleanup_expired');
            
            // Payment reconciliation
            $schedule->command('reconciliation:run')
                ->dailyAt('02:00')
                ->timezone('Africa/Nairobi')
                ->name('reconciliation.run');
            
            // Note: Subscription renewals are handled by Pesapal recurring billing,
            // not by scheduled commands.
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
