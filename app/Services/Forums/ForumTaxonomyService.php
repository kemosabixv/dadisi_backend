<?php

namespace App\Services\Forums;

use App\Models\ForumCategory;
use App\Models\ForumTag;
use App\Services\Contracts\ForumTaxonomyServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class ForumTaxonomyService implements ForumTaxonomyServiceContract
{
    /**
     * List forum categories.
     */
    public function listCategories(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = ForumCategory::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get forum category by ID.
     */
    public function getCategoryById(int $id): ForumCategory
    {
        return ForumCategory::findOrFail($id);
    }

    /**
     * Create forum category.
     */
    public function createCategory(array $data, ?Authenticatable $actor = null): ForumCategory
    {
        $data['created_by'] = $actor?->getAuthIdentifier();
        if (!isset($data['slug'])) {
            $data['slug'] = \Str::slug($data['name']);
        }

        return ForumCategory::create($data);
    }

    /**
     * Update forum category.
     */
    public function updateCategory(ForumCategory $category, array $data): ForumCategory
    {
        $category->update($data);
        return $category->fresh();
    }

    /**
     * Delete forum category.
     */
    public function deleteCategory(ForumCategory $category): bool
    {
        return $category->delete();
    }

    /**
     * List forum tags.
     */
    public function listTags(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = ForumTag::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get forum tag by ID.
     */
    public function getTagById(int $id): ForumTag
    {
        return ForumTag::findOrFail($id);
    }

    /**
     * Create forum tag.
     */
    public function createTag(array $data, ?Authenticatable $actor = null): ForumTag
    {
        $data['created_by'] = $actor?->getAuthIdentifier();
        if (!isset($data['slug'])) {
            $data['slug'] = \Str::slug($data['name']);
        }

        return ForumTag::create($data);
    }

    /**
     * Update forum tag.
     */
    public function updateTag(ForumTag $tag, array $data): ForumTag
    {
        $tag->update($data);
        return $tag->fresh();
    }

    /**
     * Delete forum tag.
     */
    public function deleteTag(ForumTag $tag): bool
    {
        return $tag->delete();
    }
}
