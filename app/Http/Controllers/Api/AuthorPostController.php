<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAuthorPostRequest;
use App\Http\Requests\Api\UpdateAuthorPostRequest;
use App\Services\Contracts\PostServiceContract;
use App\Exceptions\PostException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @group Blog - Author
 */
class AuthorPostController extends Controller
{
    protected PostServiceContract $postService;

    public function __construct(PostServiceContract $postService)
    {
        $this->postService = $postService;
        $this->middleware('auth:sanctum');
    }

    /**
     * List Author's Posts
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'search', 'category', 'tag']);
            $filters['per_page'] = $request->get('per_page', 15);

            $posts = $this->postService->listAuthorPosts(auth()->user(), $filters);

            return response()->json([
                'success' => true,
                'data' => $posts->items(),
                'pagination' => [
                    'total' => $posts->total(),
                    'per_page' => $posts->perPage(),
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                ],
                'message' => 'Author posts retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('AuthorPostController index failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve posts'], 500);
        }
    }

    /**
     * Get Post Creation Metadata
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function create(): JsonResponse
    {
        try {
            $metadata = $this->postService->getPostMetadata();

            return response()->json([
                'success' => true,
                'data' => $metadata
            ]);
        } catch (\Exception $e) {
            Log::error('AuthorPostController create failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve metadata'], 500);
        }
    }

    /**
     * Store Author Post
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function store(StoreAuthorPostRequest $request): JsonResponse
    {
        try {
            $post = $this->postService->createPost(auth()->user(), $request->validated());

            return response()->json([
                'success' => true,
                'data' => $post,
                'message' => 'Post created successfully'
            ], 201);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('AuthorPostController store failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create post'], 500);
        }
    }

    /**
     * Show Author Post
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function show(string|int $slug): JsonResponse
    {
        try {
            $post = $this->postService->getAuthorPost(auth()->user(), $slug);

            return response()->json([
                'success' => true,
                'data' => $post
            ]);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('AuthorPostController show failed', ['error' => $e->getMessage(), 'slug' => $slug]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve post'], 500);
        }
    }

    /**
     * Get Edit Data
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function edit(string|int $slug): JsonResponse
    {
        try {
            $data = $this->postService->getAuthorPostWithMetadata(auth()->user(), $slug);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('AuthorPostController edit failed', ['error' => $e->getMessage(), 'slug' => $slug]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve post data'], 500);
        }
    }

    /**
     * Update Author Post
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function update(UpdateAuthorPostRequest $request, string|int $slug): JsonResponse
    {
        try {
            $post = $this->postService->updateAuthorPost(auth()->user(), $slug, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Post updated successfully.',
                'data' => $post
            ]);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('AuthorPostController update failed', ['error' => $e->getMessage(), 'slug' => $slug]);
            return response()->json(['success' => false, 'message' => 'Failed to update post'], 500);
        }
    }

    /**
     * Delete Author Post (Soft)
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function destroy(string|int $slug): JsonResponse
    {
        try {
            $this->postService->deleteAuthorPost(auth()->user(), $slug);

            return response()->json([
                'success' => true,
                'message' => 'Post deleted successfully.'
            ]);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('AuthorPostController destroy failed', ['error' => $e->getMessage(), 'slug' => $slug]);
            return response()->json(['success' => false, 'message' => 'Failed to delete post'], 500);
        }
    }

    /**
     * Restore Author Post
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function restore(string|int $slug): JsonResponse
    {
        try {
            $post = $this->postService->restoreAuthorPost(auth()->user(), $slug);

            return response()->json([
                'success' => true,
                'message' => 'Post restored successfully.',
                'data' => $post
            ]);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('AuthorPostController restore failed', ['error' => $e->getMessage(), 'slug' => $slug]);
            return response()->json(['success' => false, 'message' => 'Failed to restore post'], 500);
        }
    }

    /**
     * Publish Author Post
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function publish(string|int $slug): JsonResponse
    {
        try {
            $post = $this->postService->publishAuthorPost(auth()->user(), $slug);

            return response()->json([
                'success' => true,
                'message' => 'Post published successfully.',
                'data' => $post
            ]);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('AuthorPostController publish failed', ['error' => $e->getMessage(), 'slug' => $slug]);
            return response()->json(['success' => false, 'message' => 'Failed to publish post'], 500);
        }
    }

    /**
     * Unpublish Author Post
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function unpublish(string|int $slug): JsonResponse
    {
        try {
            $post = $this->postService->unpublishAuthorPost(auth()->user(), $slug);

            return response()->json([
                'success' => true,
                'message' => 'Post unpublished successfully.',
                'data' => $post
            ]);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('AuthorPostController unpublish failed', ['error' => $e->getMessage(), 'slug' => $slug]);
            return response()->json(['success' => false, 'message' => 'Failed to unpublish post'], 500);
        }
    }
}
