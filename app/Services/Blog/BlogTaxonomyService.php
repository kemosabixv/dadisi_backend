<?php

namespace App\Services\Blog;

use App\Models\Category;
use App\Models\Tag;
use App\Models\AuditLog;
use App\Services\Contracts\BlogTaxonomyServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BlogTaxonomyService implements BlogTaxonomyServiceContract
{
    /**
     * Categories
     */
    public function listCategories(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Category::query();

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function createCategory(Authenticatable $actor, array $data): Category
    {
        try {
            return DB::transaction(function () use ($actor, $data) {
                $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
                $data['created_by'] = $actor->getAuthIdentifier();

                $category = Category::create($data);

                AuditLog::create([
                    'action' => 'created_category',
                    'model_type' => Category::class,
                    'model_id' => $category->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'new_values' => $category->toArray(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Created category: " . $category->name,
                ]);

                return $category;
            });
        } catch (\Exception $e) {
            Log::error('Category creation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function updateCategory(Authenticatable $actor, Category $category, array $data): Category
    {
        try {
            return DB::transaction(function () use ($actor, $category, $data) {
                $oldValues = $category->toArray();
                
                if (isset($data['name']) && !isset($data['slug'])) {
                    $data['slug'] = Str::slug($data['name']);
                }

                $category->update($data);

                AuditLog::create([
                    'action' => 'updated_category',
                    'model_type' => Category::class,
                    'model_id' => $category->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'old_values' => $oldValues,
                    'new_values' => $data,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Updated category: " . $category->name,
                ]);

                return $category;
            });
        } catch (\Exception $e) {
            Log::error('Category update failed', ['error' => $e->getMessage(), 'id' => $category->id]);
            throw $e;
        }
    }

    public function deleteCategory(Authenticatable $actor, Category $category): bool
    {
        try {
            return DB::transaction(function () use ($actor, $category) {
                AuditLog::create([
                    'action' => 'deleted_category',
                    'model_type' => Category::class,
                    'model_id' => $category->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'old_values' => $category->toArray(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Deleted category: " . $category->name,
                ]);
                return $category->delete();
            });
        } catch (\Exception $e) {
            Log::error('Category deletion failed', ['error' => $e->getMessage(), 'id' => $category->id]);
            throw $e;
        }
    }

    public function requestCategoryDeletion(Authenticatable $actor, Category $category): bool
    {
        try {
            return DB::transaction(function () use ($actor, $category) {
                $category->requestDeletion($actor->getAuthIdentifier());
                
                AuditLog::create([
                    'action' => 'requested_category_deletion',
                    'model_type' => Category::class,
                    'model_id' => $category->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Requested deletion for category: " . $category->name,
                ]);
                return true;
            });
        } catch (\Exception $e) {
            Log::error('Category deletion request failed', ['error' => $e->getMessage(), 'id' => $category->id]);
            throw $e;
        }
    }

    public function getPublicCategories(): \Illuminate\Support\Collection
    {
        return Category::query()
            ->withCount(['posts' => fn($q) => $q->published()])
            ->having('posts_count', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'description' => $cat->description,
                'post_count' => $cat->posts_count,
            ]);
    }

    /**
     * Tags
     */
    public function listTags(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = Tag::query();

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->withCount('posts')->latest()->paginate($perPage);
    }

    public function createTag(Authenticatable $actor, array $data): Tag
    {
        try {
            return DB::transaction(function () use ($actor, $data) {
                $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
                $data['created_by'] = $actor->getAuthIdentifier();

                $tag = Tag::create($data);

                AuditLog::create([
                    'action' => 'created_tag',
                    'model_type' => Tag::class,
                    'model_id' => $tag->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'new_values' => $tag->toArray(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Created tag: " . $tag->name,
                ]);

                return $tag;
            });
        } catch (\Exception $e) {
            Log::error('Tag creation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function updateTag(Authenticatable $actor, Tag $tag, array $data): Tag
    {
        try {
            return DB::transaction(function () use ($actor, $tag, $data) {
                $oldValues = $tag->toArray();

                if (isset($data['name']) && !isset($data['slug'])) {
                    $data['slug'] = Str::slug($data['name']);
                }

                $tag->update($data);

                AuditLog::create([
                    'action' => 'updated_tag',
                    'model_type' => Tag::class,
                    'model_id' => $tag->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'old_values' => $oldValues,
                    'new_values' => $data,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Updated tag: " . $tag->name,
                ]);

                return $tag;
            });
        } catch (\Exception $e) {
            Log::error('Tag update failed', ['error' => $e->getMessage(), 'id' => $tag->id]);
            throw $e;
        }
    }

    public function deleteTag(Authenticatable $actor, Tag $tag): bool
    {
        try {
            return DB::transaction(function () use ($actor, $tag) {
                AuditLog::create([
                    'action' => 'deleted_tag',
                    'model_type' => Tag::class,
                    'model_id' => $tag->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'old_values' => $tag->toArray(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Deleted tag: " . $tag->name,
                ]);
                return $tag->delete();
            });
        } catch (\Exception $e) {
            Log::error('Tag deletion failed', ['error' => $e->getMessage(), 'id' => $tag->id]);
            throw $e;
        }
    }

    public function requestTagDeletion(Authenticatable $actor, Tag $tag): bool
    {
        try {
            return DB::transaction(function () use ($actor, $tag) {
                $tag->requestDeletion($actor->getAuthIdentifier());
                
                AuditLog::create([
                    'action' => 'requested_tag_deletion',
                    'model_type' => Tag::class,
                    'model_id' => $tag->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Requested deletion for tag: " . $tag->name,
                ]);
                return true;
            });
        } catch (\Exception $e) {
            Log::error('Tag deletion request failed', ['error' => $e->getMessage(), 'id' => $tag->id]);
            throw $e;
        }
    }

    public function getPublicTags(): \Illuminate\Support\Collection
    {
        return Tag::query()
            ->withCount(['posts' => fn($q) => $q->published()])
            ->having('posts_count', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(fn($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'post_count' => $tag->posts_count,
            ]);
    }

    /**
     * Deletion Reviews
     */
    public function listPendingDeletions(?string $type = null): array
    {
        $results = [];

        if (!$type || $type === 'category') {
            $categories = Category::pendingDeletion()
                ->with('deletionRequester:id,username,email')
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'type' => 'category',
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'requested_at' => $c->requested_deletion_at?->toIso8601String(),
                    'requested_by' => $c->deletionRequester,
                    'post_count' => $c->post_count,
                ]);
            $results = array_merge($results, $categories->toArray());
        }

        if (!$type || $type === 'tag') {
            $tags = Tag::pendingDeletion()
                ->with('deletionRequester:id,username,email')
                ->get()
                ->map(fn($t) => [
                    'id' => $t->id,
                    'type' => 'tag',
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'requested_at' => $t->requested_deletion_at?->toIso8601String(),
                    'requested_by' => $t->deletionRequester,
                    'post_count' => $t->post_count,
                ]);
            $results = array_merge($results, $tags->toArray());
        }

        usort($results, fn($a, $b) => strcmp($b['requested_at'] ?? '', $a['requested_at'] ?? ''));

        return $results;
    }

    public function approveDeletion(Authenticatable $actor, string $type, int $id, ?string $comment = null): bool
    {
        try {
            return DB::transaction(function () use ($actor, $type, $id, $comment) {
                $model = match ($type) {
                    'category' => Category::find($id),
                    'tag' => Tag::find($id),
                    default => null,
                };

                if (!$model || !$model->requested_deletion_at) {
                    return false;
                }

                AuditLog::create([
                    'action' => 'approved_deletion',
                    'model_type' => get_class($model),
                    'model_id' => $model->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'old_values' => $model->toArray(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Approved deletion for {$type}: " . $model->name . ". Comment: " . $comment,
                ]);

                return $model->delete();
            });
        } catch (\Exception $e) {
            Log::error('Approval of deletion failed', ['error' => $e->getMessage(), 'type' => $type, 'id' => $id]);
            throw $e;
        }
    }

    public function rejectDeletion(Authenticatable $actor, string $type, int $id, ?string $comment = null): bool
    {
        try {
            return DB::transaction(function () use ($actor, $type, $id, $comment) {
                $model = match ($type) {
                    'category' => Category::find($id),
                    'tag' => Tag::find($id),
                    default => null,
                };

                if (!$model || !$model->requested_deletion_at) {
                    return false;
                }

                $model->clearDeletionRequest();

                AuditLog::create([
                    'action' => 'rejected_deletion',
                    'model_type' => get_class($model),
                    'model_id' => $model->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Rejected deletion for {$type}: " . $model->name . ". Comment: " . $comment,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Rejection of deletion failed', ['error' => $e->getMessage(), 'type' => $type, 'id' => $id]);
            throw $e;
        }
    }
}
