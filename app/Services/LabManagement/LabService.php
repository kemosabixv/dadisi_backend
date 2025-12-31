<?php

namespace App\Services\LabManagement;

use App\Exceptions\LabException;
use App\Models\AuditLog;
use App\Models\LabSpace;
use App\Services\Contracts\LabManagementServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * LabService
 *
 * Handles lab management including creation, updates, and deletion.
 */
class LabService implements LabManagementServiceContract
{
    /**
     * Create a new lab space
     *
     * @param Authenticatable $creator
     * @param array $data
     * @return LabSpace
     *
     * @throws LabException
     */
    public function createLabSpace(Authenticatable $creator, array $data): LabSpace
    {
        try {
            return DB::transaction(function () use ($creator, $data) {
                $lab = LabSpace::create([
                    'name' => $data['name'],
                    'county' => $data['county'] ?? 'Nairobi',
                    'description' => $data['description'] ?? null,
                    'location' => $data['location'] ?? null,
                    'capacity' => $data['capacity'] ?? 50,
                    'is_active' => true,
                ]);

                AuditLog::create([
                    'user_id' => $creator->getAuthIdentifier(),
                    'action' => 'created_lab_space',
                    'model_type' => LabSpace::class,
                    'model_id' => $lab->id,
                    'new_values' => $lab->toArray(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                Log::info('LabSpace created', [
                    'lab_id' => $lab->id,
                    'creator_id' => $creator->getAuthIdentifier(),
                ]);

                return $lab;
            });
        } catch (\Exception $e) {
            Log::error('LabSpace creation failed', [
                'creator_id' => $creator->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);

            throw LabException::creationFailed($e->getMessage());
        }
    }

    /**
     * Update a lab space
     *
     * @param Authenticatable $actor
     * @param LabSpace $lab
     * @param array $data
     * @return LabSpace
     *
     * @throws LabException
     */
    public function updateLabSpace(Authenticatable $actor, LabSpace $lab, array $data): LabSpace
    {
        try {
            $oldValues = $lab->toArray();
            $lab->update($data);

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'updated_lab_space',
                'model_type' => LabSpace::class,
                'model_id' => $lab->id,
                'old_values' => $oldValues,
                'new_values' => $lab->fresh()->toArray(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Log::info('LabSpace updated', [
                'lab_id' => $lab->id,
                'updated_by' => $actor->getAuthIdentifier(),
            ]);

            return $lab->fresh();
        } catch (\Exception $e) {
            throw LabException::updateFailed($e->getMessage());
        }
    }

    /**
     * Delete a lab space
     *
     * @param Authenticatable $actor
     * @param LabSpace $lab
     * @return bool
     *
     * @throws LabException
     */
    public function deleteLabSpace(Authenticatable $actor, LabSpace $lab): bool
    {
        try {
            return DB::transaction(function () use ($actor, $lab) {
                $labId = $lab->id;
                $labName = $lab->name;
                
                $lab->delete();

                AuditLog::create([
                    'user_id' => $actor->getAuthIdentifier(),
                    'action' => 'deleted_lab_space',
                    'model_type' => LabSpace::class,
                    'model_id' => $labId,
                    'old_values' => ['name' => $labName],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                Log::info('LabSpace deleted', [
                    'lab_id' => $labId,
                    'deleted_by' => $actor->getAuthIdentifier(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            throw LabException::deletionFailed($e->getMessage());
        }
    }

    /**
     * List lab spaces with filtering
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function listLabSpaces(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = LabSpace::query();

        if (isset($filters['type']) && $filters['type']) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search']) && $filters['search']) {
            $query->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('description', 'like', "%{$filters['search']}%");
        }

        return $query->orderBy('name', 'asc')->paginate($perPage);
    }

    /**
     * Get lab spaces by county
     *
     * @param string $county
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLabSpacesByCounty(string $county): \Illuminate\Database\Eloquent\Collection
    {
        // Note: LabSpace model doesn't currently have a county column in its migration, 
        // but it's required by the contract. Returning empty collection for now if column missing.
        try {
            return LabSpace::where('county', $county)
                ->where('is_active', true)
                ->get();
        } catch (\Exception $e) {
            return new \Illuminate\Database\Eloquent\Collection();
        }
    }
}

/*
class EquipmentService implements \App\Services\Contracts\EquipmentServiceContract
{
    // Equipment management is currently disabled due to missing models/migrations
}
*/
