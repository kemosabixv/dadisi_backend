<?php

namespace App\Services\Contracts;

use App\DTOs\CreateCountyDTO;
use App\DTOs\UpdateCountyDTO;
use App\Models\County;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * County Service Contract
 *
 * Defines the interface for county operations including CRUD and listing.
 */
interface CountyServiceContract
{
    /**
     * Get all counties
     *
     * @param array $filters Optional filters
     * @return \Illuminate\Support\Collection
     */
    public function listCounties(array $filters = []);

    /**
     * Get a county by ID
     *
     * @param County $county
     * @return County
     */
    public function getCounty(County $county): County;

    /**
     * Create a new county
     *
     * @param CreateCountyDTO $dto County data
     * @return County
     */
    public function createCounty(CreateCountyDTO $dto): County;

    /**
     * Update a county
     *
     * @param County $county
     * @param UpdateCountyDTO $dto County data
     * @return County
     */
    public function updateCounty(County $county, UpdateCountyDTO $dto): County;

    /**
     * Delete a county
     *
     * @param County $county
     * @return bool
     */
    public function deleteCounty(County $county): bool;
}
