<?php

namespace App\Services\Forums;

use App\Exceptions\ForumException;
use App\Models\AuditLog;
use App\Models\ForumThread;
use App\Models\ForumPost;
use App\Models\ForumCategory;
use App\Models\ForumTag;
use App\Models\Group;
use App\Models\User;
use App\Models\PlanSubscription;
use App\Services\Contracts\ForumServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ThreadService
 *
 * Handles forum thread operations including creation,
 * updating, locking, pinning, and deletion.
 */
class ThreadService implements ForumServiceContract
{
    /**
     * Get thread by ID
     */
    public function getThread(int $id): ForumThread
    {
        try {
            return ForumThread::with(['user', 'category', 'county', 'tags'])->findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw ForumException::threadNotFound((string)$id);
        }
    }

    /**
     * Get post count for thread
     */
    public function getPostCount(int $threadId): int
    {
        return ForumPost::where('thread_id', $threadId)->count();
    }

    /**
     * Create a new thread with initial post
     *
     * @param Authenticatable $author
     * @param array $data
     * @return ForumThread
     *
     * @throws ForumException
     */
    public function createThread(Authenticatable $author, array $data): ForumThread
    {
        // Check feature-gating quota for non-staff users
        if ($author instanceof User && !$author->isStaffMember()) {
            $this->validateThreadQuota($author);
        }

        try {
            return DB::transaction(function () use ($author, $data) {
                $categoryId = $data['category_id'] ?? null;
                if (!$categoryId && isset($data['category'])) {
                    $categoryId = ForumCategory::where('slug', $data['category'])->value('id');
                }

                $thread = ForumThread::create([
                    'user_id' => $author->getAuthIdentifier(),
                    'category_id' => $categoryId,
                    'county_id' => $data['county_id'] ?? null,
                    'title' => $data['title'],
                    'slug' => Str::slug($data['title']) . '-' . Str::random(6),
                    'is_pinned' => $data['is_pinned'] ?? false,
                    'is_locked' => false,
                    'views_count' => 0,
                    'posts_count' => 0,
                ]);

                // Create initial post (thread body)
                if (!empty($data['content'])) {
                    $post = $thread->posts()->create([
                        'user_id' => $author->getAuthIdentifier(),
                        'content' => $data['content'],
                    ]);

                    $thread->update([
                        'last_post_id' => $post->id,
                        'posts_count' => 1,
                    ]);
                }

                // Sync tags if provided
                if (!empty($data['tag_ids'])) {
                    $thread->syncTags($data['tag_ids']);
                }

                $this->logAudit($author, 'created_thread', ForumThread::class, $thread->id, [
                    'title' => $thread->title,
                    'category_id' => $thread->category_id,
                ]);

                Log::info('Forum thread created', [
                    'thread_id' => $thread->id,
                    'author_id' => $author->getAuthIdentifier(),
                ]);

                return $thread->load(['user', 'category', 'county']);
            });
        } catch (\Exception $e) {
            Log::error('Thread creation failed', [
                'author_id' => $author->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);

            throw ForumException::threadCreationFailed($e->getMessage());
        }
    }

    /**
     * Update a thread
     *
     * @param Authenticatable $actor
     * @param ForumThread $thread
     * @param array $data
     * @return ForumThread
     *
     * @throws ForumException
     */
    public function updateThread(Authenticatable $actor, ForumThread $thread, array $data): ForumThread
    {
        try {
            $updateData = [];

            if (isset($data['title'])) {
                $updateData['title'] = $data['title'];
                // Only update slug if title changed significantly
            }

            if (isset($data['category_id'])) {
                $updateData['category_id'] = $data['category_id'];
            } elseif (isset($data['category'])) {
                $updateData['category_id'] = ForumCategory::where('slug', $data['category'])->value('id');
            }

            if (isset($data['county_id'])) {
                $updateData['county_id'] = $data['county_id'];
            }

            // Moderation fields - only for authorized users
            if (isset($data['is_pinned'])) {
                $updateData['is_pinned'] = $data['is_pinned'];
            }

            if (isset($data['is_locked'])) {
                $updateData['is_locked'] = $data['is_locked'];
            }

            if (!empty($updateData)) {
                $thread->update($updateData);
            }

            // Sync tags if provided
            if (isset($data['tag_ids'])) {
                $thread->syncTags($data['tag_ids']);
            }

            $this->logAudit($actor, 'updated_thread', ForumThread::class, $thread->id, $updateData);

            Log::info('Thread updated', [
                'thread_id' => $thread->id,
                'updated_by' => $actor->getAuthIdentifier(),
            ]);

            return $thread->fresh(['user', 'category', 'county', 'tags']);
        } catch (\Exception $e) {
            throw ForumException::threadUpdateFailed($e->getMessage());
        }
    }

    /**
     * Lock a thread
     *
     * @param Authenticatable $actor
     * @param ForumThread $thread
     * @return ForumThread
     *
     * @throws ForumException
     */
    public function lockThread(Authenticatable $actor, ForumThread $thread): ForumThread
    {
        try {
            $thread->update(['is_locked' => true]);

            $this->logAudit($actor, 'locked_thread', ForumThread::class, $thread->id, ['is_locked' => true]);

            Log::info('Thread locked', [
                'thread_id' => $thread->id,
                'locked_by' => $actor->getAuthIdentifier(),
            ]);

            return $thread->fresh();
        } catch (\Exception $e) {
            throw ForumException::threadLockFailed($e->getMessage());
        }
    }

    /**
     * Unlock a thread
     *
     * @param Authenticatable $actor
     * @param ForumThread $thread
     * @return ForumThread
     *
     * @throws ForumException
     */
    public function unlockThread(Authenticatable $actor, ForumThread $thread): ForumThread
    {
        try {
            $thread->update(['is_locked' => false]);

            $this->logAudit($actor, 'unlocked_thread', ForumThread::class, $thread->id, ['is_locked' => false]);

            Log::info('Thread unlocked', [
                'thread_id' => $thread->id,
                'unlocked_by' => $actor->getAuthIdentifier(),
            ]);

            return $thread->fresh();
        } catch (\Exception $e) {
            throw ForumException::threadUnlockFailed($e->getMessage());
        }
    }

    /**
     * Pin a thread
     *
     * @param Authenticatable $actor
     * @param ForumThread $thread
     * @return ForumThread
     *
     * @throws ForumException
     */
    public function pinThread(Authenticatable $actor, ForumThread $thread): ForumThread
    {
        try {
            $thread->update(['is_pinned' => true]);

            $this->logAudit($actor, 'pinned_thread', ForumThread::class, $thread->id, ['is_pinned' => true]);

            Log::info('Thread pinned', [
                'thread_id' => $thread->id,
                'pinned_by' => $actor->getAuthIdentifier(),
            ]);

            return $thread->fresh();
        } catch (\Exception $e) {
            throw ForumException::threadUpdateFailed('Failed to pin thread: ' . $e->getMessage());
        }
    }

    /**
     * Unpin a thread
     *
     * @param Authenticatable $actor
     * @param ForumThread $thread
     * @return ForumThread
     *
     * @throws ForumException
     */
    public function unpinThread(Authenticatable $actor, ForumThread $thread): ForumThread
    {
        try {
            $thread->update(['is_pinned' => false]);

            $this->logAudit($actor, 'unpinned_thread', ForumThread::class, $thread->id, ['is_pinned' => false]);

            Log::info('Thread unpinned', [
                'thread_id' => $thread->id,
                'unpinned_by' => $actor->getAuthIdentifier(),
            ]);

            return $thread->fresh();
        } catch (\Exception $e) {
            throw ForumException::threadUpdateFailed('Failed to unpin thread: ' . $e->getMessage());
        }
    }

    /**
     * Delete a thread
     *
     * @param Authenticatable $actor
     * @param ForumThread $thread
     * @return bool
     *
     * @throws ForumException
     */
    public function deleteThread(Authenticatable $actor, ForumThread $thread): bool
    {
        try {
            return DB::transaction(function () use ($actor, $thread) {
                $threadData = ['title' => $thread->title, 'id' => $thread->id];

                // Soft delete the thread (posts cascade via model events or remain for reference)
                $thread->delete();

                $this->logAudit($actor, 'deleted_thread', ForumThread::class, $thread->id, $threadData);

                Log::info('Thread deleted', [
                    'thread_id' => $thread->id,
                    'deleted_by' => $actor->getAuthIdentifier(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            throw ForumException::threadDeletionFailed($e->getMessage());
        }
    }

    /**
     * List threads with filtering and pagination
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function listThreads(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ForumThread::query()
            ->with(['user:id,username,profile_picture_path', 'category:id,name,slug', 'lastPost.user:id,username'])
            ->pinnedFirst();

        // Filter by category
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Filter by category slug
        if (!empty($filters['category_slug'])) {
            $category = ForumCategory::where('slug', $filters['category_slug'])->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        // Filter by county
        if (!empty($filters['county_id'])) {
            $query->where('county_id', $filters['county_id']);
        }

        // Search by title
        if (!empty($filters['search'])) {
            $query->where('title', 'like', '%' . $filters['search'] . '%');
        }

        // Filter by user
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Filter locked/unlocked
        if (isset($filters['is_locked'])) {
            $query->where('is_locked', $filters['is_locked']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get thread by slug
     *
     * @param string $slug
     * @return ForumThread
     *
     * @throws ForumException
     */
    public function getThreadBySlug(string $slug): ForumThread
    {
        try {
            $thread = ForumThread::where('slug', $slug)
                ->with(['user:id,username,profile_picture_path', 'category:id,name,slug', 'county:id,name', 'tags'])
                ->firstOrFail();

            // Increment view count
            $thread->incrementViews();

            return $thread;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw ForumException::threadNotFound($slug);
        }
    }

    /**
     * Get thread with posts
     *
     * @param ForumThread $thread
     * @param int $perPage
     * @return array
     */
    public function getThreadWithPosts(ForumThread $thread, int $perPage = 20): array
    {
        $thread->incrementViews();

        $thread->load(['user:id,username,profile_picture_path', 'category:id,name,slug', 'county:id,name', 'tags']);

        $posts = $thread->posts()
            ->with('user:id,username,profile_picture_path')
            ->oldest()
            ->paginate($perPage);

        return [
            'thread' => $thread,
            'posts' => $posts,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getStats(): array
    {
        return [
            'total_threads' => ForumThread::count(),
            'total_posts' => ForumPost::count(),
            'total_categories' => ForumCategory::count(),
            'total_tags' => ForumTag::count(),
            'total_groups' => Group::count(),
            'total_members' => User::count(),
            'threads_trend' => $this->getTrend(ForumThread::class),
            'posts_trend' => $this->getTrend(ForumPost::class),
            'recent_activity' => $this->getRecentActivity(),
        ];
    }

    /**
     * Get a simple trend (count from last 30 days vs previous 30 days).
     */
    private function getTrend(string $modelClass): int
    {
        $last30 = $modelClass::where('created_at', '>=', now()->subDays(30))->count();
        $prev30 = $modelClass::where('created_at', '>=', now()->subDays(60))
            ->where('created_at', '<', now()->subDays(30))
            ->count();

        if ($prev30 === 0) return $last30 > 0 ? 100 : 0;
        
        return (int) (($last30 - $prev30) / $prev30 * 100);
    }

    /**
     * Get recent forum activity.
     */
    private function getRecentActivity(): array
    {
        return ForumThread::with(['user:id,username', 'category:id,name'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($thread) {
                return [
                    'id' => $thread->id,
                    'type' => 'thread',
                    'title' => $thread->title,
                    'user' => $thread->user->username ?? 'Unknown',
                    'category' => $thread->category->name ?? 'None',
                    'created_at' => $thread->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Validate thread creation quota for non-staff users
     *
     * @param User $user
     * @throws ForumException
     */
    private function validateThreadQuota(User $user): void
    {
        // Get user's active subscription
        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();

        if (!$subscription) {
            throw ForumException::create('You must have an active subscription to start discussions.');
        }

        // Get the plan's thread creation limit
        $plan = $subscription->plan;
        $threadLimit = $plan->getFeatureValue('forum_thread_limit', 0);

        // Limit of 0 means not allowed, -1 or extremely high means unlimited
        if ((int)$threadLimit === 0) {
            throw ForumException::create('Your current plan does not include the ability to start forum discussions.');
        }

        if ((int)$threadLimit > 0) {
            // Count threads created this month by this user
            $threadsThisMonth = ForumThread::where('user_id', $user->id)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();

            if ($threadsThisMonth >= (int)$threadLimit) {
                throw ForumException::create(
                    "You have reached your monthly discussion limit of {$threadLimit}. " .
                    "Please try again next month or upgrade your plan."
                );
            }
        }
    }

    /**
     * Log audit action
     */
    private function logAudit(Authenticatable $actor, string $action, string $modelType, int $modelId, array $changes = []): void
    {
        try {
            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'old_values' => null,
                'new_values' => !empty($changes) ? json_encode($changes) : null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log audit for thread action', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
