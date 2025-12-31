<?php

namespace App\Services\Contracts;

use App\Models\EventCategory;
use App\Models\EventTag;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

/**
 * EventTaxonomyServiceContract
 *
 * Handles management of event categories and tags.
 */
interface EventTaxonomyServiceContract
{
    /**
     * Categories
     */
    public function listCategories(array $filters = []): Collection;
    public function createCategory(Authenticatable $actor, array $data): EventCategory;
    public function updateCategory(Authenticatable $actor, EventCategory $category, array $data): EventCategory;
    public function deleteCategory(Authenticatable $actor, EventCategory $category): bool;

    /**
     * Tags
     */
    public function listTags(): Collection;
    public function createTag(Authenticatable $actor, array $data): EventTag;
    public function deleteTag(Authenticatable $actor, EventTag $tag): bool;
}
