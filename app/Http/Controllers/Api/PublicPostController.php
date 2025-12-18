<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PublicPostController extends Controller
{
    /**
     * List Published Posts
     *
     * Retrieves a paginated list of all publicly available blog posts.
     * Supports extensive filtering options to build category pages, tag archives, or search results.
     *
     * @group Blog Content (Public)
     * @groupDescription Publicly accessible endpoints for retrieving, searching, and reading blog content. No authentication is required for reading.
     * @unauthenticated
     *
     * @queryParam category_id integer optional Filter by Category ID. Example: 5
     * @queryParam tag_id integer optional Filter by Tag ID. Example: 12
     * @queryParam county_id integer optional Filter by specific County context. Example: 47
     * @queryParam search string optional Keyword search against title and excerpt. Example: "farm management"
     * @queryParam sort string optional Sort order: `latest` (default), `oldest`, or `views`. Example: views
     * @queryParam per_page integer optional Number of posts per page (default 20). Example: 10
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "Getting Started with Laravel",
     *       "slug": "getting-started-with-laravel",
     *       "excerpt": "Learn the basics...",
     *       "author": {"id": 1, "name": "John Doe"},
     *       "categories": [{"id": 1, "name": "Tutorial"}],
     *       "tags": [{"id": 1, "name": "laravel"}],
     *       "views_count": 150,
     *       "published_at": "2025-12-04T10:00:00Z",
     *       "is_featured": true
     *     }
     *   ],
     *   "pagination": {"total": 42, "per_page": 20, "current_page": 1}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $query = Post::published()
            ->with(['author:id,username,email', 'categories', 'tags', 'county:id,name']);

        // Filtering
        if ($request->has('category_id')) {
            $query->whereHas('categories', fn($q) => $q->where('category_id', $request->category_id));
        }

        if ($request->has('tag_id')) {
            $query->whereHas('tags', fn($q) => $q->where('tag_id', $request->tag_id));
        }

        if ($request->has('county_id')) {
            $query->where('county_id', $request->county_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(fn($q) => $q->where('title', 'like', "%{$search}%")->orWhere('excerpt', 'like', "%{$search}%"));
        }

        // Sorting
        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'oldest' => $query->oldest('published_at'),
            'views' => $query->orderByDesc('views_count'),
            default => $query->latest('published_at'),
        };

        $posts = $query->paginate($request->per_page ?? 20);
        $response = array_merge($posts->toArray(), [
            'success' => true,
        ]);

        return response()->json($response);
    }

    /**
     * View Single Post
     *
     * Retrieves the full content of a specific blog post.
     * This endpoint automatically increments the "views count" for the post.
     * It also returns a list of related posts to encourage continued reading.
     *
     * @group Blog Content (Public)
     * @unauthenticated
     *
     * @urlParam post string required The ID or Slug of the post to retrieve. Example: getting-started-with-laravel

     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "title": "Getting Started with Laravel",
     *     "slug": "getting-started-with-laravel",
     *     "excerpt": "Learn the basics of Laravel framework",
     *     "content": "<p>Full HTML content here...</p>",
     *     "author": {"id": 1, "name": "John Doe", "email": "john@example.com"},
     *     "categories": [{"id": 1, "name": "Tutorial", "slug": "tutorial"}],
     *     "tags": [{"id": 1, "name": "laravel"}],
     *     "media": [{"id": 1, "file_name": "image.jpg", "file_path": "/posts/image.jpg"}],
     *     "views_count": 150,
     *     "published_at": "2025-12-04T10:00:00Z",
     *     "related_posts": [{"id": 2, "title": "Advanced Laravel"}]
     *   }
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "Post not found"
     * }
     */
    public function show($post): JsonResponse
    {
        // Find by slug or ID
        $post = Post::published()
            ->where(fn($q) => $q->where('slug', $post)->orWhere('id', $post))
            ->with([
                'author:id,username,email',
                'categories',
                'tags',
                'media',
                'county:id,name',
            ])
            ->first();

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found',
            ], 404);
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
    }

    /**
     * List My Posts (Author)
     *
     * Retrieves all posts authored by the currently authenticated user.
     * This includes 'published' posts visible to everyone and 'draft' posts visible only to the author.
     *
     * @group Blog Content (Authoring)
     * @groupDescription Endpoints for authenticated authors to manage their own content (creating, updating, listing own posts).
     * @authenticated
     *
     * @queryParam status string optional Filter by status (`draft` or `published`). Example: draft
     * @queryParam search string optional Search within your own posts. Example: my first draft
     * @queryParam per_page integer optional Pagination limit. Example: 20
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "My Post",
     *       "slug": "my-post",
     *       "status": "draft",
     *       "categories": [],
     *       "tags": [],
     *       "created_at": "2025-12-04T10:00:00Z",
     *       "updated_at": "2025-12-04T10:30:00Z"
     *     }
     *   ]
     * }
     * @response 401 {
     *   "message": "Unauthenticated"
     * }
     */
    public function myPosts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Post::class);

        $query = Post::where('user_id', Auth::id())
            ->with(['categories:id,name,slug', 'tags:id,name'])
            ->select('id', 'title', 'slug', 'status', 'created_at', 'updated_at', 'published_at');

        // Filter by status
        if ($request->has('status')) {
            $status = $request->status;
            if ($status === 'draft') {
                $query->where('status', 'draft')->orWhere('published_at', null);
            } else {
                $query->where('status', $status);
            }
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('title', 'like', "%{$search}%");
        }

        $posts = $query->latest()->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $posts->items(),
            'pagination' => [
                'total' => $posts->total(),
                'per_page' => $posts->perPage(),
                'current_page' => $posts->currentPage(),
            ],
        ]);
    }

    /**
     * Update My Post
     *
     * Updates an existing post belonging to the authenticated user.
     * Authors can modify content, switch status (draft/published), and manage category or tag associations.
     *
     * @group Blog Content (Authoring)
     * @authenticated
     *
     * @urlParam post integer required The ID of the post to update. Example: 1
     * @bodyParam title string optional Updated title. Example: Advanced Techniques
     * @bodyParam slug string optional SEO-friendly URL slug. Must be unique. Example: advanced-techniques
     * @bodyParam excerpt string optional Short summary for listing pages. Example: A deep dive into...
     * @bodyParam content string optional The full HTML body of the post.
     * @bodyParam status string optional Publication status: `draft` or `published`. Example: published
     * @bodyParam category_ids array optional List of Category IDs to attach. Example: [1, 3]
     * @bodyParam tag_ids array optional List of Tag IDs to attach. Example: [2, 5]
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Post updated successfully",
     *   "data": {"id": 1, "title": "Updated Title"}
     * }
     * @response 403 {
     *   "message": "This action is unauthorized."
     * }
     */
    public function updateUserPost(Request $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        $validated = $request->validate([
            'title' => 'string|max:255',
            'slug' => 'string|max:255|unique:posts,slug,' . $post->id,
            'excerpt' => 'string|max:500',
            'content' => 'string',
            'status' => 'in:draft,published',
            'category_ids' => 'array|exists:categories,id',
            'tag_ids' => 'array|exists:tags,id',
        ]);

        $oldValues = $post->only(array_keys($validated));
        $post->update($validated);

        // Sync categories and tags
        if (isset($validated['category_ids'])) {
            $post->categories()->sync($validated['category_ids']);
        }
        if (isset($validated['tag_ids'])) {
            $post->tags()->sync($validated['tag_ids']);
        }

        $this->logAuditAction('update', Post::class, $post->id, $oldValues, $validated, "User {$post->user_id} updated own post: {$post->title}");

        return response()->json([
            'success' => true,
            'message' => 'Post updated successfully',
            'data' => $post->fresh(['categories', 'tags']),
        ]);
    }

    /**
     * Delete My Post
     *
     * Soft-deletes a post owned by the authenticated user.
     * The post will no longer be visible in public lists.
     *
     * @group Blog Content (Authoring)
     * @authenticated
     *
     * @urlParam post integer required The ID of the post to delete. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Post deleted successfully"
     * }
     * @response 403 {
     *   "message": "This action is unauthorized."
     * }
     */
    public function destroyUserPost(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $this->logAuditAction('delete', Post::class, $post->id, $post->only(['title', 'slug', 'status']), null, "User {$post->user_id} deleted own post: {$post->title}");
        $post->delete();

        return response()->json([
            'success' => true,
            'message' => 'Post deleted successfully',
        ]);
    }

    private function logAuditAction(string $action, string $modelType, int $modelId, ?array $oldValues, ?array $newValues, ?string $notes = null): void
    {
        try {
            AuditLog::create([
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'user_id' => auth()->id(),
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'notes' => $notes,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create audit log', ['error' => $e->getMessage()]);
        }
    }
}
