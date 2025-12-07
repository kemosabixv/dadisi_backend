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
     * List published posts
     *
     * @group Blog Management - Public
     * @description Retrieve all published blog posts with filtering and pagination
     *
     * @queryParam category_id Filter by category ID. Example: 1
     * @queryParam tag_id Filter by tag ID. Example: 2
     * @queryParam county_id Filter by county ID. Example: 3
     * @queryParam search Search posts by title or content. Example: tutorial
     * @queryParam sort Sort by field (latest, oldest, views). Example: latest
     * @queryParam per_page Pagination size. Example: 20
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
            ->with(['author:id,name,email', 'categories:id,name,slug', 'tags:id,name,slug', 'county:id,name'])
            ->select('id', 'title', 'slug', 'excerpt', 'author_id', 'county_id', 'is_featured', 'views_count', 'published_at', 'created_at');

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
     * View a published post
     *
     * @group Blog Management - Public
     * @description Retrieve a single published post by slug or ID. Increments view count.
     *
     * @urlParam post required Post slug or ID
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
                'author:id,name,email',
                'categories:id,name,slug',
                'tags:id,name',
                'media:id,file_name,file_path,mime_type',
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
            ->with('author:id,name')
            ->limit(3)
            ->select('id', 'title', 'slug', 'excerpt', 'author_id', 'published_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => array_merge($post->toArray(), ['related_posts' => $relatedPosts]),
        ]);
    }

    /**
     * User's own posts (draft + published)
     *
     * @group Blog Management - User
     * @authenticated
     * @description Retrieve authenticated user's posts (both draft and published)
     *
     * @queryParam status Filter by status (draft, published). Example: draft
     * @queryParam search Search user's posts. Example: tutorial
     * @queryParam per_page Pagination size. Example: 20
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

        $query = Post::where('author_id', Auth::id())
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
     * Update user's own post
     *
     * @group Blog Management - User
     * @authenticated
     * @description Update authenticated user's post (draft or published)
     *
     * @urlParam post required Post ID
     * @bodyParam title string Post title
     * @bodyParam slug string Post slug
     * @bodyParam excerpt string Short excerpt
     * @bodyParam content string Full HTML content (TinyMCE)
     * @bodyParam status string Post status (draft, published)
     * @bodyParam category_ids array Category IDs
     * @bodyParam tag_ids array Tag IDs
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

        $this->logAuditAction('update', Post::class, $post->id, $oldValues, $validated, "User {$post->author_id} updated own post: {$post->title}");

        return response()->json([
            'success' => true,
            'message' => 'Post updated successfully',
            'data' => $post->fresh(['categories', 'tags']),
        ]);
    }

    /**
     * Delete user's own post
     *
     * @group Blog Management - User
     * @authenticated
     * @description Delete authenticated user's post (soft delete)
     *
     * @urlParam post required Post ID
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

        $this->logAuditAction('delete', Post::class, $post->id, $post->only(['title', 'slug', 'status']), null, "User {$post->author_id} deleted own post: {$post->title}");
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
