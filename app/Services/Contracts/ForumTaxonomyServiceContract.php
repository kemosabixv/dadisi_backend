<?php

namespace App\Services\Contracts;

use App\Models\ForumCategory;
use App\Models\ForumTag;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;

interface ForumTaxonomyServiceContract
{
    /**
     * List forum categories.
     */
    public function listCategories(array $filters = [], int $perPage = 50): LengthAwarePaginator;

    /**
     * Get forum category by ID.
     */
    public function getCategoryById(int $id): ForumCategory;

    /**
     * Create forum category.
     */
    public function createCategory(array $data, ?Authenticatable $actor = null): ForumCategory;

    /**
     * Update forum category.
     */
    public function updateCategory(ForumCategory $category, array $data): ForumCategory;

    /**
     * Delete forum category.
     */
    public function deleteCategory(ForumCategory $category): bool;

    /**
     * List forum tags.
     */
    public function listTags(array $filters = [], int $perPage = 50): LengthAwarePaginator;

    /**
     * Get forum tag by ID.
     */
    public function getTagById(int $id): ForumTag;

    /**
     * Create forum tag.
     */
    public function createTag(array $data, ?Authenticatable $actor = null): ForumTag;

    /**
     * Update forum tag.
     */
    public function updateTag(ForumTag $tag, array $data): ForumTag;

    /**
     * Delete forum tag.
     */
    public function deleteTag(ForumTag $tag): bool;
}
