<?php

namespace App\Services\Contracts;

use App\DTOs\CreateForumThreadDTO;
use App\DTOs\UpdateForumThreadDTO;
use App\Models\ForumThread;
use App\Models\ForumPost;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * ForumServiceContract
 *
 * Contract for forum thread management
 */
interface ForumServiceContract
{
    /**
     * Create a new thread
     *
     * @param Authenticatable $author
     * @param CreateForumThreadDTO $dto
     * @return ForumThread
     */
    public function createThread(Authenticatable $author, CreateForumThreadDTO $dto): ForumThread;

    /**
     * Update a thread
     *
     * @param Authenticatable $actor
     * @param ForumThread $thread
     * @param UpdateForumThreadDTO $dto
     * @return ForumThread
     */
    public function updateThread(Authenticatable $actor, ForumThread $thread, UpdateForumThreadDTO $dto): ForumThread;

    /**
     * Lock a thread
     *
     * @param Authenticatable $actor
     * @param ForumThread $thread
     * @return ForumThread
     */
    public function lockThread(Authenticatable $actor, ForumThread $thread): ForumThread;

    /**
     * Unlock a thread
     *
     * @param Authenticatable $actor
     * @param ForumThread $thread
     * @return ForumThread
     */
    public function unlockThread(Authenticatable $actor, ForumThread $thread): ForumThread;

    /**
     * Pin a thread
     *
     * @param Authenticatable $actor
     * @param ForumThread $thread
     * @return ForumThread
     */
    public function pinThread(Authenticatable $actor, ForumThread $thread): ForumThread;

    /**
     * Unpin a thread
     *
     * @param Authenticatable $actor
     * @param ForumThread $thread
     * @return ForumThread
     */
    public function unpinThread(Authenticatable $actor, ForumThread $thread): ForumThread;

    /**
     * Delete a thread
     *
     * @param Authenticatable $actor
     * @param ForumThread $thread
     * @return bool
     */
    public function deleteThread(Authenticatable $actor, ForumThread $thread): bool;

    /**
     * List threads with filtering
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function listThreads(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get thread by slug
     *
     * @param string $slug
     * @return ForumThread
     */
    public function getThreadBySlug(string $slug): ForumThread;

    /**
     * Get thread with posts
     *
     * @param ForumThread $thread
     * @param int $perPage
     * @return array
     */
    public function getThreadWithPosts(ForumThread $thread, int $perPage = 20): array;

    /**
     * Get forum statistics
     *
     * @return array
     */
    public function getStats(): array;
}
