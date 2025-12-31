<?php

namespace App\Services;

use App\Services\Contracts\MenuServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * MenuService
 *
 * Implements logic for generating dynamic navigation menus.
 */
class MenuService implements MenuServiceContract
{
    /**
     * @inheritDoc
     */
    public function getAdminMenu(Authenticatable $user): array
    {
        /** @var \App\Models\User $user */
        // Delegate to UIPermissionService to ensure a single source of truth for menu and permissions
        return (new UIPermissionService($user))->getAuthorizedMenu();
    }

    /**
     * @inheritDoc
     */
    public function getUserMenu(Authenticatable $user): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/dashboard', 'icon' => 'Home'],
            ['key' => 'profile', 'label' => 'Profile', 'href' => '/profile', 'icon' => 'User'],
            ['key' => 'membership', 'label' => 'Membership', 'href' => '/membership', 'icon' => 'CreditCard'],
            ['key' => 'donations', 'label' => 'My Donations', 'href' => '/donations', 'icon' => 'Heart'],
        ];
    }
}
