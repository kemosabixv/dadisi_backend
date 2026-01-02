<?php

namespace App\Services\Contracts;

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;

interface BlogTaxonomyServiceContract
{
    /**
     * Categories
     */
    public function listCategories(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function createCategory(Authenticatable $actor, array $data): Category;
    public function updateCategory(Authenticatable $actor, Category $category, array $data): Category;
    public function deleteCategory(Authenticatable $actor, Category $category): bool;
    public function requestCategoryDeletion(Authenticatable $actor, Category $category): bool;
    public function getPublicCategories(): \Illuminate\Support\Collection;
    public function getAffectedPostsByCategory(Category $category, int $perPage = 15): LengthAwarePaginator;

    /**
     * Tags
     */
    public function listTags(array $filters = [], int $perPage = 50): LengthAwarePaginator;
    public function createTag(Authenticatable $actor, array $data): Tag;
    public function updateTag(Authenticatable $actor, Tag $tag, array $data): Tag;
    public function deleteTag(Authenticatable $actor, Tag $tag): bool;
    public function requestTagDeletion(Authenticatable $actor, Tag $tag): bool;
    public function getPublicTags(): \Illuminate\Support\Collection;
    public function getAffectedPostsByTag(Tag $tag, int $perPage = 15): LengthAwarePaginator;

    /**
     * Deletion Reviews
     */
    public function listPendingDeletions(?string $type = null): array;
    public function approveDeletion(Authenticatable $actor, string $type, int $id, ?string $comment = null): bool;
    public function rejectDeletion(Authenticatable $actor, string $type, int $id, ?string $comment = null): bool;
}
