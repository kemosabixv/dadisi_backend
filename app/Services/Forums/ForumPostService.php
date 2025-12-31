<?php

namespace App\Services\Forums;

use App\Exceptions\ForumException;
use App\Models\AuditLog;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\User;
use App\Models\PlanSubscription;
use App\Services\Contracts\ForumPostServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ForumPostService
 *
 * Handles forum post operations including creation,
 * updating, and deletion.
 */
class ForumPostService implements ForumPostServiceContract
{
    /**
     * @inheritDoc
     */
    public function getPost(int $id): ForumPost
    {
        try {
            return ForumPost::with(['user', 'thread'])->findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw ForumException::postNotFound((string)$id);
        }
    }

    /**
     * @inheritDoc
     */
    public function createPost(Authenticatable $author, ForumThread $thread, array $data): ForumPost
    {
        // Check feature-gating quota for non-staff users
        if ($author instanceof User && !$author->isStaffMember()) {
            $this->validateReplyQuota($author);
        }

        if ($thread->is_locked) {
            throw ForumException::threadLocked();
        }

        try {
            return DB::transaction(function () use ($author, $thread, $data) {
                $post = $thread->posts()->create([
                    'user_id' => $author->getAuthIdentifier(),
                    'content' => $data['content'],
                ]);

                // Thread stats (last_post_id, posts_count) are automatically 
                // updated via ForumPost model's static::created hook.

                $this->logAudit($author, 'created_post', ForumPost::class, $post->id, [
                    'thread_id' => $thread->id,
                ]);

                Log::info('Forum post created', [
                    'post_id' => $post->id,
                    'thread_id' => $thread->id,
                    'author_id' => $author->getAuthIdentifier(),
                ]);

                return $post->load('user');
            });
        } catch (ForumException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Post creation failed', [
                'thread_id' => $thread->id,
                'author_id' => $author->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);

            throw ForumException::postCreationFailed($e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function updatePost(Authenticatable $actor, ForumPost $post, array $data): ForumPost
    {
        try {
            $post->update([
                'content' => $data['content'],
                'is_edited' => true,
            ]);

            $this->logAudit($actor, 'updated_post', ForumPost::class, $post->id, [
                'content' => $data['content'],
            ]);

            Log::info('Forum post updated', [
                'post_id' => $post->id,
                'updated_by' => $actor->getAuthIdentifier(),
            ]);

            return $post->fresh('user');
        } catch (\Exception $e) {
            throw ForumException::postUpdateFailed($e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function deletePost(Authenticatable $actor, ForumPost $post): bool
    {
        try {
            return DB::transaction(function () use ($actor, $post) {
                $postId = $post->id;
                
                // Thread stats are automatically updated via ForumPost 
                // model's static::deleted hook.
                $post->delete();

                $this->logAudit($actor, 'deleted_post', ForumPost::class, $postId);

                Log::info('Forum post deleted', [
                    'post_id' => $postId,
                    'deleted_by' => $actor->getAuthIdentifier(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            throw ForumException::postDeletionFailed($e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function getThreadPosts(ForumThread $thread, int $perPage = 20): LengthAwarePaginator
    {
        return $thread->posts()
            ->with('user:id,username,profile_picture_path')
            ->oldest()
            ->paginate($perPage);
    }

    /**
     * Validate forum reply quota for non-staff users
     *
     * @param User $user
     * @throws ForumException
     */
    private function validateReplyQuota(User $user): void
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
            throw ForumException::create('You must have an active subscription to post replies.');
        }

        // Get the plan's reply limit
        $plan = $subscription->plan;
        $replyLimit = $plan->getFeatureValue('forum_reply_limit', 0);

        // Limit of 0 means not allowed, -1 or extremely high means unlimited
        if ((int)$replyLimit === 0) {
            throw ForumException::create('Your current plan does not include the ability to post forum replies.');
        }

        if ((int)$replyLimit > 0) {
            // Count replies created this month by this user
            $repliesThisMonth = ForumPost::where('user_id', $user->id)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();

            if ($repliesThisMonth >= (int)$replyLimit) {
                throw ForumException::create(
                    "You have reached your monthly reply limit of {$replyLimit}. " .
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
            Log::warning('Failed to log audit for forum action', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
