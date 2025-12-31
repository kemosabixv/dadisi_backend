<?php

namespace App\Services\Contracts;

/**
 * Contract for UI Permission Service
 *
 * Builds authorization-aware UI menu and permission structures.
 */
interface UIPermissionServiceContract
{
    /**
     * Get authorized menu for user
     *
     * @return array Menu structure filtered by user permissions
     */
    public function getAuthorizedMenu(): array;

    /**
     * Check if user can access a resource
     *
     * @param string $action
     * @param string $resource
     * @return bool
     */
    public function can(string $action, string $resource): bool;

    /**
     * Get all accessible features for user
     *
     * @return array
     */
    public function getAccessibleFeatures(): array;
}
