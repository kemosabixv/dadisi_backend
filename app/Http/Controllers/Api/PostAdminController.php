<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use App\Services\Contracts\PostServiceContract;
use App\Exceptions\PostException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @group Admin - Blog Posts
 */
class PostAdminController extends Controller
{
    protected PostServiceContract $postService;

    public function __construct(PostServiceContract $postService)
    {
        $this->postService = $postService;
        $this->middleware(['auth:sanctum', 'admin']);
    }

    /**
     * List all posts
     * 
     * @group Admin - Blog Posts
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Post::class);

            $filters = $request->only(['status', 'category', 'tag', 'author_id', 'search', 'county_id']);
            $perPage = (int) $request->get('per_page', 15);

            $posts = $this->postService->listPosts($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $posts->items(),
                'pagination' => [
                    'total' => $posts->total(),
                    'per_page' => $posts->perPage(),
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                ],
                'message' => 'Posts retrieved successfully'
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Exception $e) {
            Log::error('PostAdminController index failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve posts'], 500);
        }
    }

    /**
     * Get post metadata for forms
     * 
     * @group Admin - Blog Posts
     * @authenticated
     */
    public function create(): JsonResponse
    {
        try {
            $this->authorize('create', Post::class);
            $metadata = $this->postService->getPostMetadata();
            return response()->json([
                'success' => true,
                'data' => $metadata
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Exception $e) {
            Log::error('PostAdminController create failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve metadata'], 500);
        }
    }

    /**
     * Store post
     * 
     * @group Admin - Blog Posts
     * @authenticated
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Post::class);
            $post = $this->postService->createPost($request->user(), $request->validated());

            return response()->json([
                'success' => true,
                'data' => $post,
                'message' => 'Post created successfully'
            ], 201);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PostAdminController store failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create post'], 500);
        }
    }

    /**
     * Show post
     * 
     * @group Admin - Blog Posts
     * @authenticated
     */
    public function show(Post $post): JsonResponse
    {
        try {
            $post->load(['author:id,username', 'categories', 'tags', 'media', 'county:id,name']);
            $this->authorize('view', $post);

            return response()->json([
                'success' => true,
                'data' => $post
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Exception $e) {
            Log::error('PostAdminController show failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve post'], 500);
        }
    }

    /**
     * Get Edit Data
     * 
     * @group Admin - Blog Posts
     * @authenticated
     */
    public function edit(Post $post): JsonResponse
    {
        try {
            $this->authorize('update', $post);
            
            $metadata = $this->postService->getPostMetadata();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'post' => $post->load(['categories', 'tags', 'media']),
                    'metadata' => $metadata
                ]
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Exception $e) {
            Log::error('PostAdminController edit failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve metadata'], 500);
        }
    }

    /**
     * Update post
     * 
     * Update an existing blog post.
     *
     * @group Admin - Blog Posts
     * @authenticated
     * @urlParam post string required The post slug. Example: my-first-blog-post
     * @responseFile status=200 storage/responses/post-update.json
     */
    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        try {
            $this->authorize('update', $post);

            $updatedPost = $this->postService->updatePost($request->user(), $post, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $updatedPost,
                'message' => 'Post updated successfully'
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PostAdminController update failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to update post'], 500);
        }
    }

    /**
     * Delete post
     * 
     * @group Admin - Blog Posts
     * @authenticated
     */
    public function destroy(Post $post): JsonResponse
    {
        try {
            $this->authorize('delete', $post);

            $this->postService->deletePost(auth()->user(), $post);

            return response()->json([
                'success' => true,
                'message' => 'Post moved to trash'
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PostAdminController destroy failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to delete post'], 500);
        }
    }

    /**
     * Restore post
     * 
     * @group Admin - Blog Posts
     * @authenticated
     */
    public function restore(Post $post): JsonResponse
    {
        try {
            $this->authorize('restore', $post);

            $restoredPost = $this->postService->restorePost(auth()->user(), $post);

            return response()->json([
                'success' => true,
                'data' => $restoredPost,
                'message' => 'Post restored successfully'
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PostAdminController restore failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to restore post'], 500);
        }
    }

    /**
     * Force delete post
     * 
     * @group Admin - Blog Posts
     * @authenticated
     */
    public function forceDelete(Post $post): JsonResponse
    {
        try {
            $this->authorize('forceDelete', $post);

            $this->postService->forceDeletePost(auth()->user(), $post);

            return response()->json([
                'success' => true,
                'message' => 'Post permanently deleted'
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PostAdminController forceDelete failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to permanently delete post'], 500);
        }
    }

    /**
     * Publish post
     * 
     * @group Admin - Blog Posts
     * @authenticated
     */
    public function publish(Post $post): JsonResponse
    {
        try {
            $this->authorize('publish', $post);

            $publishedPost = $this->postService->publishPost(auth()->user(), $post);

            return response()->json([
                'success' => true,
                'data' => $publishedPost,
                'message' => 'Post published successfully'
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PostAdminController publish failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to publish post'], 500);
        }
    }

    /**
     * Unpublish post
     * 
     * @group Admin - Blog Posts
     * @authenticated
     */
    public function unpublish(Post $post): JsonResponse
    {
        try {
            $this->authorize('unpublish', $post);

            $unpublishedPost = $this->postService->unpublishPost(auth()->user(), $post);

            return response()->json([
                'success' => true,
                'data' => $unpublishedPost,
                'message' => 'Post reverted to draft'
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PostAdminController unpublish failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to unpublish post'], 500);
        }
    }
}
