<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ForumException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreForumPostRequest;
use App\Http\Requests\Api\UpdateForumPostRequest;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Services\Contracts\ForumPostServiceContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ForumPostController extends Controller
{
    public function __construct(
        private ForumPostServiceContract $postService
    ) {
        // Only require auth for store, update, destroy - allow public access to index (viewing posts)
        $this->middleware('auth:sanctum')->except(['index']);
    }

    /**
     * List posts for a thread.
     *
     * Retrieves paginated posts for a specific forum thread.
     *
     * @group Forum Posts
     *
     * @unauthenticated
     *
     * @urlParam thread string required The thread slug. Example: welcome-to-dadisi-forum-x1y2z3
     *
     * @queryParam per_page integer Items per page (default 20). Example: 20
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "data": [
     *       {
     *         "id": 21,
     *         "content": "Welcome everyone!",
     *         "user": {"id": 1, "username": "admin"}
     *       }
     *     ],
     *     "total": 1
     *   }
     * }
     */
    public function index(ForumThread $thread, Request $request): JsonResponse
    {
        try {
            $posts = $this->postService->getThreadPosts($thread, $request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $posts,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve forum posts', ['error' => $e->getMessage(), 'thread_id' => $thread->id]);

            return response()->json(['success' => false, 'message' => 'Failed to retrieve posts'], 500);
        }
    }

    /**
     * Create a new post.
     *
     * Adds a reply to a specific forum thread.
     * Returns the created post details with user metadata.
     *
     * @group Forum Posts
     *
     * @authenticated
     *
     * @urlParam thread string required The thread slug or ID. Example: welcome-to-dadisi-forum-x1y2z3
     *
     * @bodyParam content string required The content of the reply. Example: This is a great initiative!
     * @bodyParam parent_id integer optional ID of parent post for nested replies.
     *
     * @response 201 {
     *   "data": {
     *     "id": 21,
     *     "content": "This is a great initiative!",
     *     "user": {"id": 2, "username": "jane_doe"}
     *   },
     *   "message": "Reply posted successfully."
     * }
     */
    public function store(StoreForumPostRequest $request, ForumThread $thread): JsonResponse
    {
        try {
            $this->authorize('create', [ForumPost::class, $thread]);

            $validated = $request->validated();
            $post = $this->postService->createPost(auth()->user(), $thread, $validated);

            return response()->json([
                'success' => true,
                'data' => $post,
                'message' => 'Reply posted successfully.',
            ], 201);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to post in this thread.'], 403);
        } catch (ForumException $e) {
            // Map plan/quota errors to 403 Forbidden as expected by tests
            $isQuotaError = str_contains($e->getMessage(), 'subscription') ||
                            str_contains($e->getMessage(), 'limit') ||
                            str_contains($e->getMessage(), 'plan');
            $statusCode = ($isQuotaError || str_contains($e->getMessage(), 'locked')) ? 403 : 422;

            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to create forum post', ['error' => $e->getMessage(), 'thread_id' => $thread->id]);

            return response()->json(['success' => false, 'message' => 'Failed to create reply'], 500);
        }
    }

    /**
     * Update a post.
     *
     * @group Forum Posts
     *
     * @authenticated
     *
     * @urlParam post integer required The ID of the post.
     *
     * @bodyParam content string required The content of the post.
     */
    public function update(UpdateForumPostRequest $request, ForumPost $post): JsonResponse
    {
        try {
            $this->authorize('update', $post);

            $validated = $request->validated();
            $updatedPost = $this->postService->updatePost(auth()->user(), $post, $validated);

            return response()->json([
                'success' => true,
                'data' => $updatedPost,
                'message' => 'Post updated successfully.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to update this post.'], 403);
        } catch (ForumException $e) {
            $statusCode = str_contains($e->getMessage(), 'locked') ? 403 : 422;

            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to update forum post', ['error' => $e->getMessage(), 'post_id' => $post->id]);

            return response()->json(['success' => false, 'message' => 'Failed to update post'], 500);
        }
    }

    /**
     * Delete a post.
     *
     * @group Forum Posts
     *
     * @authenticated
     *
     * @urlParam post integer required The ID of the post.
     */
    public function destroy(ForumPost $post): JsonResponse
    {
        try {
            $this->authorize('delete', $post);

            $this->postService->deletePost(auth()->user(), $post);

            return response()->json([
                'success' => true,
                'message' => 'Post deleted successfully.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to delete this post.'], 403);
        } catch (ForumException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to delete forum post', ['error' => $e->getMessage(), 'post_id' => $post->id]);

            return response()->json(['success' => false, 'message' => 'Failed to delete post'], 500);
        }
    }
}
