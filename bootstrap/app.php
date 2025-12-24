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
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
        
        // Apply security headers globally (CSP, HSTS, X-Frame-Options)
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);
        
        // Configure API authentication to return JSON instead of redirecting
        $middleware->redirectGuestsTo(function ($request) {
            // For API routes, return null to prevent redirect and let Sanctum handle it
            if ($request->is('api/*')) {
                return null;
            }
            // Mock payment pages need to be accessible without authentication
            if ($request->is('mock-payment/*')) {
                return null;
            }
            // For other web routes, return null since we have no login page
            // (authentication is handled via the frontend SPA)
            return null;
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Automatic Exchange Rate Refresh - Daily at midnight UTC
        $schedule->command('exchange-rates:auto-refresh')
                ->dailyAt('00:00') // Every day at 00:00 UTC (midnight UTC)
                ->withoutOverlapping()
                ->runInBackground()
                ->environments(['local', 'production']) // Enable in both environments
                ->evenInMaintenanceMode(); // Continue running even during maintenance

        // Cleanup stale pending payments - Hourly
        $schedule->job(new \App\Jobs\CleanupPendingPaymentsJob())
                ->hourly()
                ->withoutOverlapping();

        // Cleanup old webhook events - Daily
        $schedule->job(new \App\Jobs\CleanupWebhookEventsJob())
                ->dailyAt('02:00')
                ->withoutOverlapping();

        // Enqueue due renewals (including retries) - Daily
        $schedule->command('renewals:enqueue-due')
                ->dailyAt('01:00')
                ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
