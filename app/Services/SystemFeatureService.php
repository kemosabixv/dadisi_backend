<?php

namespace App\Services;

use App\Models\SystemFeature;
use App\Services\Contracts\SystemFeatureServiceContract;
use Illuminate\Support\Collection;

/**
 * SystemFeatureService
 *
 * Implements business logic for system feature management.
 */
class SystemFeatureService implements SystemFeatureServiceContract
{
    /**
     * @inheritDoc
     */
    public function listFeatures(bool $activeOnly = true): Collection
    {
        $query = SystemFeature::query()->sorted();

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * @inheritDoc
     */
    public function getFeature(int $id): SystemFeature
    {
        return SystemFeature::findOrFail($id);
    }

    /**
     * @inheritDoc
     */
    public function updateFeature(SystemFeature $feature, array $data): SystemFeature
    {
        $feature->update($data);
        return $feature->fresh();
    }

    /**
     * @inheritDoc
     */
    public function toggleFeatureStatus(SystemFeature $feature): SystemFeature
    {
        $feature->update(['is_active' => !$feature->is_active]);
        return $feature->fresh();
    }
}
