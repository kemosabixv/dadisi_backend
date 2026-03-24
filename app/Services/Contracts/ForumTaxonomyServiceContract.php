<?php

namespace App\Services\Contracts;

use App\DTOs\CreateForumCategoryDTO;
use App\DTOs\UpdateForumCategoryDTO;
use App\DTOs\CreateForumTagDTO;
use App\DTOs\UpdateForumTagDTO;
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
    public function createCategory(CreateForumCategoryDTO $dto, ?Authenticatable $actor = null): ForumCategory;

    /**
     * Update forum category.
     */
    public function updateCategory(ForumCategory $category, UpdateForumCategoryDTO $dto): ForumCategory;

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
    public function createTag(CreateForumTagDTO $dto, ?Authenticatable $actor = null): ForumTag;

    /**
     * Update forum tag.
     */
    public function updateTag(ForumTag $tag, UpdateForumTagDTO $dto): ForumTag;

    /**
     * Delete forum tag.
     */
    public function deleteTag(ForumTag $tag): bool;
}
