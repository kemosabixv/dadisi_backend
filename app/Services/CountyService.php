<?php

namespace App\Services;

use App\DTOs\CreateCountyDTO;
use App\DTOs\UpdateCountyDTO;
use App\Services\Contracts\CountyServiceContract;
use App\Models\County;
use Illuminate\Support\Facades\Log;

/**
 * County Service
 *
 * Handles county-related operations including CRUD and listing.
 */
class CountyService implements CountyServiceContract
{
    /**
     * Get all counties sorted alphabetically
     */
    public function listCounties(array $filters = [])
    {
        try {
            $query = County::select('id', 'name');
            
            if (!empty($filters['search'])) {
                $query->where('name', 'like', "%{$filters['search']}%");
            }
            
            return $query->orderBy('name')->get();
        } catch (\Exception $e) {
            Log::error('Failed to retrieve counties', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get a single county
     */
    public function getCounty(County $county): County
    {
        try {
            return $county;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve county', ['error' => $e->getMessage(), 'county_id' => $county->id]);
            throw $e;
        }
    }

    /**
     * Create a new county
     */
    public function createCounty(CreateCountyDTO $dto): County
    {
        try {
            return County::create($dto->toArray());
        } catch (\Exception $e) {
            Log::error('Failed to create county', ['error' => $e->getMessage(), 'data' => $dto->toArray()]);
            throw $e;
        }
    }

    /**
     * Update an existing county
     */
    public function updateCounty(County $county, UpdateCountyDTO $dto): County
    {
        try {
            $county->update($dto->toArray());
            return $county->fresh();
        } catch (\Exception $e) {
            Log::error('Failed to update county', ['error' => $e->getMessage(), 'county_id' => $county->id]);
            throw $e;
        }
    }

    /**
     * Delete a county
     */
    public function deleteCounty(County $county): bool
    {
        try {
            return $county->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete county', ['error' => $e->getMessage(), 'county_id' => $county->id]);
            throw $e;
        }
    }
}
