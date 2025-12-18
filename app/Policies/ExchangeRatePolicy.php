<?php

namespace App\Policies;

use App\Models\ExchangeRate;
use App\Models\User;

class ExchangeRatePolicy
{
    /**
     * Determine whether the user can view exchange rate configuration.
     * Users with view_exchange_rates permission can access
     */
    public function view(?User $user): bool
    {
        // Allow public access for viewing the rate (pricing switchers)
        if (!$user) return true;
        
        return $user->hasPermissionTo('view_exchange_rates');
    }

    /**
     * Determine whether the user can view detailed exchange rate information.
     * Users with view_exchange_rates permission can access
     */
    public function viewInfo(User $user): bool
    {
        return $user->hasPermissionTo('view_exchange_rates');
    }

    /**
     * Determine whether the user can refresh exchange rates from API.
     * Users with refresh_exchange_rates permission can trigger manual API refreshes
     */
    public function refreshFromApi(User $user): bool
    {
        return $user->hasPermissionTo('refresh_exchange_rates');
    }

    /**
     * Determine whether the user can update cache settings.
     * Users with configure_exchange_rates permission can modify caching
     */
    public function updateCacheSettings(User $user): bool
    {
        return $user->hasPermissionTo('configure_exchange_rates');
    }

    /**
     * Determine whether the user can manually update exchange rates.
     * Users with manage_exchange_rates permission can set manual rates
     */
    public function updateManualRate(User $user): bool
    {
        return $user->hasPermissionTo('manage_exchange_rates');
    }
}
