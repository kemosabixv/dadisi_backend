<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Exceptions\PostException;
use App\Services\Contracts\PostServiceContract;
use App\Services\Contracts\BlogTaxonomyServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * @group Blog - Public
 */
class PublicPostController extends Controller
{
    protected PostServiceContract $postService;
    protected BlogTaxonomyServiceContract $taxonomyService;

    public function __construct(
        PostServiceContract $postService,
        BlogTaxonomyServiceContract $taxonomyService
    ) {
        $this->postService = $postService;
        $this->taxonomyService = $taxonomyService;
    }

    /**
     * List Published Posts
     * 
     * @group Blog - Public
     * @unauthenticated
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['category', 'category_id', 'tag', 'tag_id', 'county', 'county_id', 'search', 'sort']);
            $perPage = min((int) $request->input('per_page', 20), 50);

            // Normalize filters
            if (isset($filters['category']) && !isset($filters['category_id'])) $filters['category_id'] = $filters['category'];
            if (isset($filters['tag']) && !isset($filters['tag_id'])) $filters['tag_id'] = $filters['tag'];
            if (isset($filters['county']) && !isset($filters['county_id'])) $filters['county_id'] = $filters['county'];

            $posts = $this->postService->listPublishedPosts($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $posts->items(),
                'pagination' => [
                    'total' => $posts->total(),
                    'per_page' => $posts->perPage(),
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('PublicPostController index failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve posts'], 500);
        }
    }

    /**
     * View Single Post
     * 
     * @group Blog - Public
     * @unauthenticated
     */
    public function show($identifier): JsonResponse
    {
        try {
            // Identifier can be ID or Slug. Service's getBySlug handles published only.
            // If identifier is numeric, we might need a getById for public.
            // But usually we use slug for public.
            
            $post = null;
            if (is_numeric($identifier)) {
                $post = Post::published()->with(['author:id,username,email', 'categories', 'tags', 'media', 'county:id,name'])->find($identifier);
            } else {
                $post = $this->postService->getBySlug($identifier);
            }

            if (!$post || $post->status !== 'published') {
                throw PostException::notFound($identifier);
            }

            // Increment views
            $post->increment('views_count');

            // Get related posts (same category, exclude current)
            $relatedPosts = Post::published()
                ->whereHas('categories', fn($q) => $q->whereIn('category_id', $post->categories->pluck('id')))
                ->where('id', '!=', $post->id)
                ->with('author:id,username')
                ->limit(3)
                ->get();

            return response()->json([
                'success' => true,
                'data' => array_merge($post->toArray(), ['related_posts' => $relatedPosts]),
            ]);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PublicPostController show failed', ['error' => $e->getMessage(), 'identifier' => $identifier]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve post'], 500);
        }
    }

    /**
     * List My Posts (Author)
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function myPosts(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Post::class);
            
            $filters = $request->only(['status', 'search']);
            $filters['per_page'] = min((int) $request->input('per_page', 20), 50);

            $posts = $this->postService->listAuthorPosts($request->user(), $filters);

            return response()->json([
                'success' => true,
                'data' => $posts->items(),
                'pagination' => [
                    'total' => $posts->total(),
                    'per_page' => $posts->perPage(),
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Exception $e) {
            Log::error('PublicPostController myPosts failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve posts'], 500);
        }
    }

    /**
     * Update My Post
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function updateUserPost(\App\Http\Requests\Api\UpdatePublicPostRequest $request, Post $post): JsonResponse
    {
        try {
            $this->authorize('update', $post);
            
            $updatedPost = $this->postService->updatePost($request->user(), $post, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Post updated successfully',
                'data' => $updatedPost,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PublicPostController updateUserPost failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to update post'], 500);
        }
    }

    /**
     * Delete My Post
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function destroyUserPost(Post $post): JsonResponse
    {
        try {
            $this->authorize('delete', $post);

            $this->postService->deletePost(auth()->user(), $post);

            return response()->json([
                'success' => true,
                'message' => 'Post deleted successfully',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (PostException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PublicPostController destroyUserPost failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to delete post'], 500);
        }
    }

    /**
     * List Categories
     * 
     * @group Blog - Public
     * @unauthenticated
     */
    public function categories(): JsonResponse
    {
        try {
            $categories = $this->taxonomyService->getPublicCategories();

            return response()->json([
                'success' => true,
                'data' => $categories,
            ]);
        } catch (\Exception $e) {
            Log::error('PublicPostController categories failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve categories'], 500);
        }
    }

    /**
     * List Tags
     * 
     * @group Blog - Public
     * @unauthenticated
     */
    public function tags(): JsonResponse
    {
        try {
            $tags = $this->taxonomyService->getPublicTags();

            return response()->json([
                'success' => true,
                'data' => $tags,
            ]);
        } catch (\Exception $e) {
            Log::error('PublicPostController tags failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve tags'], 500);
        }
    }
}
