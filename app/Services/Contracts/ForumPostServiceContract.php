<?php

namespace App\Services\Contracts;

use App\Models\ForumPost;
use App\Models\ForumThread;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * ForumPostServiceContract
 *
 * Contract for forum post management (replies to threads)
 */
interface ForumPostServiceContract
{
    /**
     * Create a new post in a thread
     *
     * @param Authenticatable $author
     * @param ForumThread $thread
     * @param array $data
     * @return ForumPost
     */
    public function createPost(Authenticatable $author, ForumThread $thread, array $data): ForumPost;

    /**
     * Update an existing post
     *
     * @param Authenticatable $actor
     * @param ForumPost $post
     * @param array $data
     * @return ForumPost
     */
    public function updatePost(Authenticatable $actor, ForumPost $post, array $data): ForumPost;

    /**
     * Delete a post
     *
     * @param Authenticatable $actor
     * @param ForumPost $post
     * @return bool
     */
    public function deletePost(Authenticatable $actor, ForumPost $post): bool;

    /**
     * Get a post by ID
     *
     * @param int $id
     * @return ForumPost
     */
    public function getPost(int $id): ForumPost;

    /**
     * Get paginated posts for a thread
     *
     * @param ForumThread $thread
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getThreadPosts(ForumThread $thread, int $perPage = 20): LengthAwarePaginator;
}
