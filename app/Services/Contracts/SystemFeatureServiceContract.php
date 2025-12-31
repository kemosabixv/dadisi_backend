<?php

namespace App\Services\Contracts;

use App\Models\SystemFeature;
use Illuminate\Support\Collection;

/**
 * SystemFeatureServiceContract
 *
 * Defines contract for managing system features that can be associated with subscription plans.
 */
interface SystemFeatureServiceContract
{
    /**
     * List system features
     *
     * @param bool $activeOnly
     * @return Collection
     */
    public function listFeatures(bool $activeOnly = true): Collection;

    /**
     * Get a single feature
     *
     * @param int $id
     * @return SystemFeature
     */
    public function getFeature(int $id): SystemFeature;

    /**
     * Update a feature
     *
     * @param SystemFeature $feature
     * @param array $data
     * @return SystemFeature
     */
    public function updateFeature(SystemFeature $feature, array $data): SystemFeature;

    /**
     * Toggle a feature's active status
     *
     * @param SystemFeature $feature
     * @return SystemFeature
     */
    public function toggleFeatureStatus(SystemFeature $feature): SystemFeature;
}
