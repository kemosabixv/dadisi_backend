<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Cleanup orphaned media daily at 03:00
        $schedule->command('media:cleanup')->dailyAt('03:00');

        // Send renewal reminders daily at 09:00 Nairobi time
        $schedule->command('renewals:send-reminders --dueDays=0')
            ->dailyAt('09:00')
            ->timezone('Africa/Nairobi')
            ->name('renewals.send_reminders');

        // Enqueue due auto-renewals hourly
        $schedule->command('renewals:enqueue-due')
            ->hourly()
            ->timezone('Africa/Nairobi')
            ->name('renewals.enqueue_due');

        // Schedule reconciliation runs daily at 02:00 Nairobi time
        $schedule->command('reconciliation:run')
            ->dailyAt('02:00')
            ->timezone('Africa/Nairobi')
            ->name('reconciliation.run');

        // Existing cleanup job for user data can remain scheduled elsewhere if needed
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
