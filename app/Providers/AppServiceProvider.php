<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Observers\PlanSubscriptionObserver;
use App\Models\PlanSubscription;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Payment Gateway Manager for DI
        $this->app->singleton(\App\Services\PaymentGateway\GatewayManager::class, function ($app) {
            return new \App\Services\PaymentGateway\GatewayManager();
        });

        // Register QR Code Facade Alias
        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            \Illuminate\Foundation\AliasLoader::getInstance()->alias(
                'QrCode', 
                \SimpleSoftwareIO\QrCode\Facades\QrCode::class
            );
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Bind permission and role route parameters to lookup by id (not name)
        Route::bind('permission', function ($value) {
            return Permission::findOrFail($value);
        });

        Route::bind('role', function ($value) {
            return Role::findOrFail($value);
        });

        // Register model observers for data integrity
        PlanSubscription::observe(PlanSubscriptionObserver::class);
    }
}
