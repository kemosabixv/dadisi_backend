<?php

namespace App\Services\Contracts;

use App\Models\Post;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\Paginator;

/**
 * PostServiceContract
 *
 * Defines contract for blog post management including creation,
 * updates, publishing, and content management.
 */
interface PostServiceContract
{
    /**
     * List all posts (Admin view)
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listPosts(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    /**
     * List author's posts
     *
     * @param Authenticatable $author
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listAuthorPosts(Authenticatable $author, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    /**
     * Get post creation/edit metadata (categories, tags)
     *
     * @return array
     */
    public function getPostMetadata(): array;

    /**
     * Get a specific post for an author
     *
     * @param Authenticatable $author
     * @param int $postId
     * @return Post
     */
    public function getAuthorPost(Authenticatable $author, int $postId): Post;

    /**
     * Get author post with metadata for editing
     *
     * @param Authenticatable $author
     * @param int $postId
     * @return array
     */
    public function getAuthorPostWithMetadata(Authenticatable $author, int $postId): array;

    /**
     * Create a new post
     *
     * @param Authenticatable $author The post author
     * @param array $data Post data
     * @return Post The created post
     */
    public function createPost(Authenticatable $author, array $data): Post;

    /**
     * Update a post
     *
     * @param Authenticatable $actor User updating
     * @param Post $post The post
     * @param array $data Update data
     * @return Post Updated post
     */
    public function updatePost(Authenticatable $actor, Post $post, array $data): Post;

    /**
     * Update author's post
     *
     * @param Authenticatable $author
     * @param int $postId
     * @param array $data
     * @return Post
     */
    public function updateAuthorPost(Authenticatable $author, int $postId, array $data): Post;

    /**
     * Delete a post (soft)
     *
     * @param Authenticatable $actor User deleting
     * @param Post $post The post
     * @return bool
     */
    public function deletePost(Authenticatable $actor, Post $post): bool;

    /**
     * Delete author's post
     *
     * @param Authenticatable $author
     * @param int $postId
     * @return bool
     */
    public function deleteAuthorPost(Authenticatable $author, int $postId): bool;

    /**
     * Restore a deleted post
     *
     * @param Authenticatable $actor User restoring
     * @param Post $post The post
     * @return Post
     */
    public function restorePost(Authenticatable $actor, Post $post): Post;

    /**
     * Restore author's post
     *
     * @param Authenticatable $author
     * @param int $postId
     * @return Post
     */
    public function restoreAuthorPost(Authenticatable $author, int $postId): Post;

    /**
     * Permanently delete a post
     *
     * @param Authenticatable $actor
     * @param Post $post
     * @return bool
     */
    public function forceDeletePost(Authenticatable $actor, Post $post): bool;

    /**
     * Publish a post
     *
     * @param Authenticatable $actor User publishing
     * @param Post $post The post
     * @return Post Published post
     */
    public function publishPost(Authenticatable $actor, Post $post): Post;

    /**
     * Publish author's post
     *
     * @param Authenticatable $author
     * @param int $postId
     * @return Post
     */
    public function publishAuthorPost(Authenticatable $author, int $postId): Post;

    /**
     * Unpublish a post
     *
     * @param Authenticatable $actor User unpublishing
     * @param Post $post The post
     * @return Post Unpublished post
     */
    public function unpublishPost(Authenticatable $actor, Post $post): Post;

    /**
     * Unpublish author's post
     *
     * @param Authenticatable $author
     * @param int $postId
     * @return Post
     */
    public function unpublishAuthorPost(Authenticatable $author, int $postId): Post;

    /**
     * Get post by slug
     *
     * @param string $slug Post slug
     * @return Post The post
     */
    public function getBySlug(string $slug): Post;

    /**
     * List published posts with filtering
     *
     * @param array $filters Filters
     * @param int $perPage Results per page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator Paginated posts
     */
    public function listPublishedPosts(array $filters = [], int $perPage = 10): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    /**
     * Get author's posts (Collection)
     *
     * @param Authenticatable $author The author
     * @param bool $publishedOnly Only published posts
     * @param int $limit Maximum results
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAuthorPosts(Authenticatable $author, bool $publishedOnly = true, int $limit = 50): \Illuminate\Database\Eloquent\Collection;
}
