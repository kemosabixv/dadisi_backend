<?php

namespace App\Services\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * MenuServiceContract
 *
 * Defines contract for generating dynamic navigation menus based on user permissions.
 */
interface MenuServiceContract
{
    /**
     * Get admin menu items for a user
     *
     * @param Authenticatable $user
     * @return array
     */
    public function getAdminMenu(Authenticatable $user): array;

    /**
     * Get user dashboard menu items
     *
     * @param Authenticatable $user
     * @return array
     */
    public function getUserMenu(Authenticatable $user): array;
}
