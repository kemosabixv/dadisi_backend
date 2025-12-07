<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }
}
