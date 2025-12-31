<?php

namespace App\Services\Events;

use App\Models\AuditLog;
use App\Models\EventCategory;
use App\Models\EventTag;
use App\Services\Contracts\EventTaxonomyServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EventTaxonomyService implements EventTaxonomyServiceContract
{
    /**
     * Categories
     */
    public function listCategories(array $filters = []): Collection
    {
        $query = EventCategory::query()->with('children');

        if (isset($filters['parent_only']) && $filters['parent_only']) {
            $query->whereNull('parent_id');
        }

        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->active();
        }

        return $query->orderBy('sort_order')->orderBy('name')->get();
    }

    public function createCategory(Authenticatable $actor, array $data): EventCategory
    {
        try {
            return DB::transaction(function () use ($actor, $data) {
                if (!isset($data['slug'])) {
                    $data['slug'] = Str::slug($data['name']);
                }

                $category = EventCategory::create($data);

                AuditLog::create([
                    'action' => 'created_event_category',
                    'model_type' => EventCategory::class,
                    'model_id' => $category->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'new_values' => $category->toArray(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return $category;
            });
        } catch (\Exception $e) {
            Log::error('Event category creation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function updateCategory(Authenticatable $actor, EventCategory $category, array $data): EventCategory
    {
        try {
            return DB::transaction(function () use ($actor, $category, $data) {
                $oldValues = $category->toArray();
                
                if (isset($data['name']) && !isset($data['slug'])) {
                    $data['slug'] = Str::slug($data['name']);
                }

                $category->update($data);

                AuditLog::create([
                    'action' => 'updated_event_category',
                    'model_type' => EventCategory::class,
                    'model_id' => $category->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'old_values' => $oldValues,
                    'new_values' => $category->fresh()->toArray(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return $category->fresh();
            });
        } catch (\Exception $e) {
            Log::error('Event category update failed', ['error' => $e->getMessage(), 'id' => $category->id]);
            throw $e;
        }
    }

    public function deleteCategory(Authenticatable $actor, EventCategory $category): bool
    {
        try {
            return DB::transaction(function () use ($actor, $category) {
                $categoryName = $category->name;
                $categoryId = $category->id;

                $category->delete();

                AuditLog::create([
                    'action' => 'deleted_event_category',
                    'model_type' => EventCategory::class,
                    'model_id' => $categoryId,
                    'user_id' => $actor->getAuthIdentifier(),
                    'old_values' => ['name' => $categoryName],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Event category deletion failed', ['error' => $e->getMessage(), 'id' => $category->id]);
            throw $e;
        }
    }

    /**
     * Tags
     */
    public function listTags(): Collection
    {
        return EventTag::orderBy('name')->get();
    }

    public function createTag(Authenticatable $actor, array $data): EventTag
    {
        try {
            return DB::transaction(function () use ($actor, $data) {
                if (!isset($data['slug'])) {
                    $data['slug'] = Str::slug($data['name']);
                }

                $tag = EventTag::create($data);

                AuditLog::create([
                    'action' => 'created_event_tag',
                    'model_type' => EventTag::class,
                    'model_id' => $tag->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'new_values' => $tag->toArray(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return $tag;
            });
        } catch (\Exception $e) {
            Log::error('Event tag creation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function deleteTag(Authenticatable $actor, EventTag $tag): bool
    {
        try {
            return DB::transaction(function () use ($actor, $tag) {
                $tagName = $tag->name;
                $tagId = $tag->id;

                $tag->delete();

                AuditLog::create([
                    'action' => 'deleted_event_tag',
                    'model_type' => EventTag::class,
                    'model_id' => $tagId,
                    'user_id' => $actor->getAuthIdentifier(),
                    'old_values' => ['name' => $tagName],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Event tag deletion failed', ['error' => $e->getMessage(), 'id' => $tag->id]);
            throw $e;
        }
    }
}
