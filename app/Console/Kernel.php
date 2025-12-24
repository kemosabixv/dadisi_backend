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
                    case 'hourly':
                        $command->hourly();
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
            
            // Fallback schedules
            $schedule->command('media:cleanup-temporary')->hourly();
            $schedule->command('renewals:send-reminders --dueDays=0')
                ->dailyAt('09:00')
                ->timezone('Africa/Nairobi')
                ->name('renewals.send_reminders');
            $schedule->command('renewals:enqueue-due')
                ->hourly()
                ->timezone('Africa/Nairobi')
                ->name('renewals.enqueue_due');
            $schedule->command('reconciliation:run')
                ->dailyAt('02:00')
                ->timezone('Africa/Nairobi')
                ->name('reconciliation.run');
            $schedule->command('subscriptions:process-expired')
                ->dailyAt('06:00')
                ->timezone('Africa/Nairobi')
                ->name('subscriptions.process_expired');
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
