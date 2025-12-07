<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // middleware registered via app/Http/Kernel
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Automatic Exchange Rate Refresh - Daily at midnight UTC
        $schedule->command('exchange-rates:auto-refresh')
                ->dailyAt('00:00') // Every day at 00:00 UTC (midnight UTC)
                ->withoutOverlapping()
                ->runInBackground()
                ->environments(['local', 'production']) // Enable in both environments
                ->evenInMaintenanceMode(); // Continue running even during maintenance
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
