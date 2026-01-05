<?php

namespace App\Services\Blog;

use App\Exceptions\PostException;
use App\Models\AuditLog;
use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\County;
use App\Services\Contracts\PostServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PostService
 *
 * Handles blog post management including creation, updates,
 * publishing, and content lifecycle management.
 */
class PostService implements PostServiceContract
{
    public function listPosts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Post::with(['author:id,username', 'categories', 'tags', 'media', 'county:id,name']);

        if (($filters['status'] ?? null) === 'trashed') {
            $query->onlyTrashed();
        } elseif (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['county_id'])) {
            $query->where('county_id', $filters['county_id']);
        }

        if (isset($filters['category'])) {
            $query->whereHas('categories', function($q) use ($filters) {
                $q->where('categories.slug', $filters['category'])->orWhere('categories.id', $filters['category']);
            });
        }

        if (isset($filters['tag'])) {
            $query->whereHas('tags', function($q) use ($filters) {
                $q->where('tags.slug', $filters['tag'])->orWhere('tags.id', $filters['tag']);
            });
        }

        if (isset($filters['author_id'])) {
            $query->where('user_id', $filters['author_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        return $query->latest('created_at')->paginate($perPage);
    }

    public function listAuthorPosts(Authenticatable $author, array $filters = []): LengthAwarePaginator
    {
        $filters['author_id'] = $author->getAuthIdentifier();
        return $this->listPosts($filters, $filters['per_page'] ?? 15);
    }

    public function getPostMetadata(): array
    {
        return [
            'categories' => Category::select('id', 'name')->orderBy('name')->get(),
            'tags' => Tag::select('id', 'name')->orderBy('name')->get(),
            'counties' => County::select('id', 'name')->orderBy('name')->get(),
        ];
    }

    public function getAuthorPost(Authenticatable $author, string|int $identifier): Post
    {
        try {
            $query = Post::withTrashed()
                ->with(['author:id,username', 'categories', 'tags', 'media', 'county:id,name']);

            // Find post by ID or slug
            if (is_numeric($identifier)) {
                $post = $query->findOrFail((int)$identifier);
            } else {
                $post = $query->where('slug', $identifier)->firstOrFail();
            }

            // Explicit ownership check for 403 instead of 404
            if ($post->user_id !== $author->getAuthIdentifier()) {
                throw PostException::unauthorized();
            }

            return $post;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw PostException::notFound((string)$identifier);
        }
    }

    public function getAuthorPostWithMetadata(Authenticatable $author, string|int $identifier): array
    {
        $post = $this->getAuthorPost($author, $identifier);
        $metadata = $this->getPostMetadata();
        
        return array_merge(['post' => $post], $metadata);
    }

    public function createPost(Authenticatable $author, array $data): Post
    {
        // Check feature-gating quota for non-staff users
        if (!$author->isStaffMember()) {
            $this->validateBlogCreationQuota($author);
        }

            try {
                return DB::transaction(function () use ($author, $data) {
                    // Strip domain from hero_image_path if present
                    $heroImagePath = $data['hero_image_path'] ?? null;
                    if ($heroImagePath) {
                        // Remove http://localhost:8000 or any domain prefix
                        $heroImagePath = preg_replace('#^https?://[^/]+#', '', $heroImagePath);
                    }

                    $postData = [
                        'user_id' => $author->getAuthIdentifier(),
                        'county_id' => $data['county_id'] ?? null,
                        'title' => $data['title'],
                        'slug' => $data['slug'] ?? Str::slug($data['title']),
                        'excerpt' => $data['excerpt'] ?? (isset($data['content']) ? Str::limit(strip_tags($data['content']), 200) : (isset($data['body']) ? Str::limit(strip_tags($data['body']), 200) : null)),
                        'body' => $data['content'] ?? $data['body'] ?? '',
                        'status' => $data['status'] ?? 'draft',
                        'published_at' => ($data['status'] ?? 'draft') === 'published' ? now() : null,
                        'hero_image_path' => $heroImagePath,
                        'meta_title' => $data['meta_title'] ?? null,
                        'meta_description' => $data['meta_description'] ?? null,
                        'is_featured' => $data['is_featured'] ?? false,
                    ];

                    // Ensure slug uniqueness
                    if (!isset($data['slug'])) {
                        $baseSlug = $postData['slug'];
                        $count = Post::where('slug', 'like', $baseSlug . '%')->count();
                        if ($count > 0) {
                            $postData['slug'] = $baseSlug . '-' . ($count + 1);
                        }
                    }

                    $post = Post::create($postData);

                    if (isset($data['category_ids'])) {
                        $post->categories()->sync($data['category_ids']);
                    } elseif (isset($data['category'])) {
                        $category = Category::where('slug', $data['category'])
                            ->orWhere('name', $data['category'])
                            ->first();
                        if ($category) {
                            $post->categories()->sync([$category->id]);
                        }
                    }

                    if (isset($data['tag_ids'])) {
                        $post->tags()->sync($data['tag_ids']);
                    }

                    if (isset($data['media_ids'])) {
                        $post->media()->sync($data['media_ids']);
                        // Mark all attached media as belonging to this post
                        \App\Models\Media::whereIn('id', $data['media_ids'])
                            ->where('user_id', $post->user_id)
                            ->update([
                                'attached_to' => 'post',
                                'attached_to_id' => $post->id,
                            ]);
                        $post->updateAttachedMediaPrivacy();
                    }

                    // Mark hero image as attached to this post (so it doesn't show in media library)
                    if (!empty($heroImagePath)) {
                        $this->attachMediaToPost($post, $heroImagePath);
                    }

                    AuditLog::create([
                        'action' => 'created_post',
                        'model_type' => Post::class,
                        'model_id' => $post->id,
                        'user_id' => $author->getAuthIdentifier(),
                        'new_values' => $postData,
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'notes' => "Created post: " . $post->title,
                    ]);

                    Log::info('Post created', [
                        'post_id' => $post->id,
                        'created_by' => $author->getAuthIdentifier(),
                    ]);

                    return $post->load(['author:id,username', 'categories', 'tags', 'media']);
                });
        } catch (\Exception $e) {
            Log::error('Post creation failed', ['error' => $e->getMessage()]);
            throw PostException::creationFailed($e->getMessage());
        }
    }

    public function updatePost(Authenticatable $actor, Post $post, array $data): Post
    {
        try {
            return DB::transaction(function () use ($actor, $post, $data) {
                $oldValues = $post->toArray();
                
                if (isset($data['content'])) {
                    $data['body'] = $data['content'];
                    unset($data['content']);
                }

                // Auto-regenerate slug when title changes
                if (isset($data['title']) && !isset($data['slug'])) {
                    $data['slug'] = \Illuminate\Support\Str::slug($data['title']);
                }

                $post->update($data);

                if (isset($data['category_ids'])) {
                    $post->categories()->sync($data['category_ids']);
                } elseif (isset($data['category'])) {
                    $category = Category::where('slug', $data['category'])
                        ->orWhere('name', $data['category'])
                        ->first();
                    if ($category) {
                        $post->categories()->sync([$category->id]);
                    }
                }

                if (isset($data['tag_ids'])) {
                    $post->tags()->sync($data['tag_ids']);
                }

                if (array_key_exists('media_ids', $data)) {
                    $post->media()->sync($data['media_ids']);
                    $post->updateAttachedMediaPrivacy();
                }

                if (!empty($data['hero_image_path'])) {
                    $this->markMediaAsPermanent($data['hero_image_path']);
                }

                AuditLog::create([
                    'action' => 'updated_post',
                    'model_type' => Post::class,
                    'model_id' => $post->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'old_values' => $oldValues,
                    'new_values' => $data,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Updated post: " . $post->title,
                ]);

                Log::info('Post updated', [
                    'post_id' => $post->id,
                    'updated_by' => $actor->getAuthIdentifier(),
                ]);

                return $post->fresh(['author:id,username', 'categories', 'tags', 'media']);
            });
        } catch (\Exception $e) {
            Log::error('Post update failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
            throw PostException::updateFailed($e->getMessage());
        }
    }

    public function updateAuthorPost(Authenticatable $author, string|int $identifier, array $data): Post
    {
        $post = $this->getAuthorPost($author, $identifier);
        return $this->updatePost($author, $post, $data);
    }

    public function deletePost(Authenticatable $actor, Post $post): bool
    {
        try {
            return DB::transaction(function () use ($actor, $post) {
                AuditLog::create([
                    'action' => 'deleted_post',
                    'model_type' => Post::class,
                    'model_id' => $post->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'old_values' => $post->only(['title', 'slug', 'status']),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Deleted post: " . $post->title,
                ]);
                return $post->delete();
            });
        } catch (\Exception $e) {
            Log::error('Post deletion failed', ['error' => $e->getMessage()]);
            throw PostException::deletionFailed($e->getMessage());
        }
    }

    public function deleteAuthorPost(Authenticatable $author, string|int $identifier): bool
    {
        $post = $this->getAuthorPost($author, $identifier);
        return $this->deletePost($author, $post);
    }

    public function restorePost(Authenticatable $actor, Post $post): Post
    {
        try {
            if (!$post->trashed()) {
                throw PostException::notDeleted();
            }

            return DB::transaction(function () use ($actor, $post) {
                $post->restore();
                AuditLog::create([
                    'action' => 'restored_post',
                    'model_type' => Post::class,
                    'model_id' => $post->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'new_values' => $post->only(['status']),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Restored post: " . $post->title,
                ]);
                return $post;
            });
        } catch (\Exception $e) {
            Log::error('Post restoration failed', ['error' => $e->getMessage()]);
            throw PostException::restorationFailed($e->getMessage());
        }
    }

    public function restoreAuthorPost(Authenticatable $author, string|int $identifier): Post
    {
        $post = $this->getAuthorPost($author, $identifier);
        return $this->restorePost($author, $post);
    }

    public function forceDeletePost(Authenticatable $actor, Post $post): bool
    {
        try {
            return DB::transaction(function () use ($actor, $post) {
                AuditLog::create([
                    'action' => 'permanently_deleted_post',
                    'model_type' => Post::class,
                    'model_id' => $post->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'old_values' => $post->only(['title', 'slug']),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Permanently deleted post: " . $post->title,
                ]);
                return $post->forceDelete();
            });
        } catch (\Exception $e) {
            Log::error('Post permanent deletion failed', ['error' => $e->getMessage()]);
            throw PostException::deletionFailed($e->getMessage());
        }
    }

    public function publishPost(Authenticatable $actor, Post $post): Post
    {
        try {
            if ($post->status === 'published') {
                throw PostException::alreadyPublished();
            }

            return DB::transaction(function () use ($actor, $post) {
                $oldStatus = $post->status;
                $post->update([
                    'status' => 'published',
                    'published_at' => $post->published_at ?? now(),
                ]);

                AuditLog::create([
                    'action' => 'published_post',
                    'model_type' => Post::class,
                    'model_id' => $post->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'old_values' => ['status' => $oldStatus],
                    'new_values' => ['status' => 'published'],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Published post: " . $post->title,
                ]);

                return $post->fresh();
            });
        } catch (\Exception $e) {
            if ($e instanceof PostException) throw $e;
            Log::error('Post publish failed', ['error' => $e->getMessage()]);
            throw PostException::publishFailed($e->getMessage());
        }
    }

    public function publishAuthorPost(Authenticatable $author, string|int $identifier): Post
    {
        $post = $this->getAuthorPost($author, $identifier);
        return $this->publishPost($author, $post);
    }

    public function unpublishPost(Authenticatable $actor, Post $post): Post
    {
        try {
            if ($post->status !== 'published') {
                throw PostException::unpublishFailed('Post is not published', 400);
            }

            return DB::transaction(function () use ($actor, $post) {
                $oldStatus = $post->status;
                $post->update(['status' => 'draft', 'published_at' => null]);

                AuditLog::create([
                    'action' => 'unpublished_post',
                    'model_type' => Post::class,
                    'model_id' => $post->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'old_values' => ['status' => $oldStatus],
                    'new_values' => ['status' => 'draft'],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'notes' => "Unpublished post: " . $post->title,
                ]);

                return $post->fresh();
            });
        } catch (\Exception $e) {
            if ($e instanceof PostException) throw $e;
            Log::error('Post unpublish failed', ['error' => $e->getMessage()]);
            throw PostException::unpublishFailed($e->getMessage());
        }
    }

    public function unpublishAuthorPost(Authenticatable $author, string|int $identifier): Post
    {
        $post = $this->getAuthorPost($author, $identifier);
        return $this->unpublishPost($author, $post);
    }

    public function getBySlug(string $slug): Post
    {
        try {
            return Post::published()
                ->where('slug', $slug)
                ->with(['author:id,username', 'categories', 'tags', 'media', 'county:id,name'])
                ->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw PostException::notFound($slug);
        }
    }

    public function listPublishedPosts(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Post::published()
            ->with(['author:id,username', 'categories', 'tags', 'media', 'county:id,name']);

        $categoryFilter = $filters['category_id'] ?? $filters['category'] ?? null;
        if ($categoryFilter) {
            $query->whereHas('categories', function($q) use ($categoryFilter) {
                $q->where('categories.id', $categoryFilter)
                  ->orWhere('categories.slug', $categoryFilter);
            });
        }

        $tagFilter = $filters['tag_id'] ?? $filters['tag'] ?? null;
        if ($tagFilter) {
            $query->whereHas('tags', function($q) use ($tagFilter) {
                $q->where('tags.id', $tagFilter)
                  ->orWhere('tags.slug', $tagFilter);
            });
        }

        if (isset($filters['author_id']) && $filters['author_id']) {
            $query->where('user_id', $filters['author_id']);
        }

        if (isset($filters['county_id']) && $filters['county_id']) {
            $countyFilter = $filters['county_id'];
            $query->whereHas('county', function($q) use ($countyFilter) {
                // Support both ID and Name/Code for county
                $q->where('counties.id', $countyFilter)
                  ->orWhere('counties.name', 'like', "%{$countyFilter}%")
                  ->orWhere('counties.code', $countyFilter);
            });
        }

        if (isset($filters['search']) && $filters['search']) {
            $query->search($filters['search']);
        }

        return $query->orderBy('published_at', 'desc')->paginate($perPage);
    }

    public function getAuthorPosts(Authenticatable $author, bool $publishedOnly = true, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        $query = Post::where('user_id', $author->getAuthIdentifier());

        if ($publishedOnly) {
            $query->published();
        }

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Validate blog creation quota for non-staff users
     * 
     * Checks if user has an active subscription with remaining quota
     * for creating posts this month.
     * 
     * @throws PostException when quota is exceeded
     */
    private function validateBlogCreationQuota(Authenticatable $user): void
    {
        // Get user's active subscription
        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->latest('starts_at')
            ->first();

        if (!$subscription || !$subscription->isActive()) {
            throw PostException::create('You must have an active subscription to create posts.');
        }

        // Get the plan's blog creation limit
        $plan = $subscription->plan;
        $blogCreationLimit = (int)$plan->getFeatureValue('blog-posts-monthly', 0);

        if ($blogCreationLimit === 0) {
            throw PostException::create('Your plan does not include the ability to create blog posts.');
        }

        // Count posts created this month by this user
        $postsThisMonth = Post::where('user_id', $user->getAuthIdentifier())
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        if ($postsThisMonth >= $blogCreationLimit) {
            throw PostException::create(
                "You have reached your monthly blog post limit of {$blogCreationLimit}. " .
                "Please try again next month or upgrade your plan."
            );
        }
    }

    /**
     * Attach media to a post so it doesn't appear in the user's media library.
     * Sets attached_to and attached_to_id to mark it as an entity-specific image.
     */
    private function attachMediaToPost(Post $post, string $heroImagePath): void
    {
        try {
            // Extract file path from the URL (remove /storage prefix)
            $filePath = preg_replace('#^/storage#', '', $heroImagePath);
            
            // Find media by file_path and mark it as attached to this post
            \App\Models\Media::where('file_path', $filePath)
                ->where('user_id', $post->user_id)
                ->update([
                    'attached_to' => 'post',
                    'attached_to_id' => $post->id,
                    'temporary_until' => null, // Mark as permanent
                ]);
        } catch (\Exception $e) {
            Log::error('Failed to attach media to post', [
                'post_id' => $post->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
