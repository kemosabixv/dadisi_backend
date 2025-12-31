<?php

namespace App\Services\Contracts;

use App\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;

interface CategoryServiceContract
{
    /**
     * List all categories with optional filters.
     */
    public function listCategories(array $filters = [], int $perPage = 50): LengthAwarePaginator;

    /**
     * Get a single category by ID.
     */
    public function getCategoryById(int $id): Category;

    /**
     * Create a new category.
     */
    public function createCategory(array $data): Category;

    /**
     * Update an existing category.
     */
    public function updateCategory(Category $category, array $data): Category;

    /**
     * Delete a category.
     */
    public function deleteCategory(Category $category): bool;

    /**
     * Get category metadata for creation form.
     */
    public function getCreationMetadata(): array;

    /**
     * Get category metadata for editing form.
     */
    public function getEditMetadata(Category $category): array;
}
