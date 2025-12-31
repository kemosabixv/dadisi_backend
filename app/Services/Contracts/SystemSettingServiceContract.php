<?php

namespace App\Services\Contracts;

use Illuminate\Support\Collection;

/**
 * SystemSettingServiceContract
 *
 * Defines contract for global system settings management.
 */
interface SystemSettingServiceContract
{
    /**
     * Get all settings with optional group filter
     *
     * @param string|null $group
     * @return Collection Key-value pairs of settings
     */
    public function getSettings(?string $group = null): Collection;

    /**
     * Update multiple settings at once
     *
     * @param array $settings Key-value pairs
     * @param int|null $userId User ID performing the update
     * @return array Updated settings
     */
    public function updateSettings(array $settings, ?int $userId = null): array;

    /**
     * Get a specific setting value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Set a specific setting value
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $userId
     * @return void
     */
    public function set(string $key, $value, ?int $userId = null): void;
}
