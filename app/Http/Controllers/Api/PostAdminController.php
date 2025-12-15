<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\AuditLog;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PostAdminController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Post::class, 'post');
    }

    /**
     * Show form data for creating a new post
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Get form metadata needed to create a post (categories, tags, etc)
     *
     * @response {
     *   "success": true,
     *   "data": {
     *     "categories": [{"id": 1, "name": "Technology"}],
     *     "tags": [{"id": 1, "name": "Laravel"}]
     *   }
     * }
     */
    public function create(): JsonResponse
    {
        $this->authorize('create', Post::class);

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => Category::select('id', 'name')->orderBy('name')->get(),
                'tags' => Tag::select('id', 'name')->orderBy('name')->get(),
            ],
        ]);
    }

    /**
     * List all posts (Admin view)
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description List all posts with full details (admins/editors only)
     *
     * @queryParam status Filter by status (draft, published). Example: published
     * @queryParam county_id Filter by county ID. Example: 1
     * @queryParam author_id Filter by author ID. Example: 5
     * @queryParam search Search in title or body. Example: welcome
     * @queryParam per_page Pagination size. Example: 15
     * @queryParam page Page number. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "Welcome Post",
     *       "slug": "welcome-post",
     *       "body": "...",
     *       "status": "published",
     *       "published_at": "2025-01-01T00:00:00Z",
     *       "author": {"id": 1, "name": "Author Name"},
     *       "categories": [{"id": 1, "name": "News"}],
     *       "tags": [{"id": 1, "name": "welcome"}]
     *     }
     *   ],
     *   "pagination": {"total": 50, "per_page": 15, "current_page": 1}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Post::class);
        
        $query = Post::with('author:id,name,username', 'categories:id,name', 'tags:id,name', 'county:id,name');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('county_id')) {
            $query->where('county_id', $request->county_id);
        }

        if ($request->has('author_id')) {
            $query->where('user_id', $request->author_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('title', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
        }

        $posts = $query->latest('created_at')->paginate($request->per_page ?? 15);

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
    }

    /**
     * Store a new post
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Create a new blog post (editors/admins/premium authors)
     *
     * @bodyParam title string required Post title. Example: Welcome to Our Blog
     * @bodyParam content string required Post content (HTML). Example: <p>Post content here</p>
     * @bodyParam slug string Slug (auto-generated if omitted). Example: welcome-to-blog
     * @bodyParam excerpt string Post summary. Example: A brief introduction
     * @bodyParam status string Post status (draft, published). Default: draft
     * @bodyParam county_id integer County ID. Example: 1
     * @bodyParam category_ids array Category IDs. Example: [1, 2]
     * @bodyParam tag_ids array Tag IDs. Example: [1, 3, 5]
     * @bodyParam meta_title string SEO title (max 60). Example: Welcome - Our Blog
     * @bodyParam meta_description string SEO description (max 160). Example: A great blog post
     * @bodyParam is_featured boolean Feature the post. Example: true
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Post created successfully",
     *   "data": {"id": 1, "title": "Welcome Post", "slug": "welcome-post", "status": "draft"}
     * }
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Map content to body for database storage
        if (isset($validated['content'])) {
            $validated['body'] = $validated['content'];
            unset($validated['content']);
        }

        // Generate slug from title if not provided
        if (!isset($validated['slug'])) {
            $validated['slug'] = \Str::slug($validated['title']);
            // Ensure uniqueness
            $count = Post::where('slug', 'like', $validated['slug'] . '%')->count();
            if ($count > 0) {
                $validated['slug'] .= '-' . ($count + 1);
            }
        }

        $validated['user_id'] = auth()->id();

        $post = Post::create($validated);

        // Attach categories and tags
        if (isset($validated['category_ids'])) {
            $post->categories()->sync($validated['category_ids']);
        }
        if (isset($validated['tag_ids'])) {
            $post->tags()->sync($validated['tag_ids']);
        }

        // Attach media files if provided
        if (isset($validated['media_ids']) && is_array($validated['media_ids'])) {
            $post->media()->sync($validated['media_ids']);
            // Update media privacy based on post status
            $post->updateAttachedMediaPrivacy();
        }

        $this->logAuditAction('create', Post::class, $post->id, null, $post->only(['title', 'slug', 'status']), "Created post: {$post->title}");

        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => $post->load('author:id,name,username', 'categories:id,name', 'tags:id,name', 'media:id,file_name,file_path,type'),
        ], 201);
    }

    /**
     * Display a specific post (admin view)
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Get detailed post information (admins/editors/owner)
     *
     * @urlParam post required The post ID
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "title": "Welcome Post",
     *     "slug": "welcome-post",
     *     "body": "...",
     *     "status": "published",
     *     "author": {"id": 1, "name": "Author"}
     *   }
     * }
     */
    public function show(Post $post): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $post->load('author:id,name,username', 'categories:id,name', 'tags:id,name', 'media:id,file_name,file_path', 'county:id,name'),
        ]);
    }

    /**
     * Show edit form data for a post
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Get post data and form metadata needed to edit a post
     *
     * @urlParam post integer required The post ID
     *
     * @response {
     *   "success": true,
     *   "data": {
     *     "post": {"id": 1, "title": "...", "content": "..."},
     *     "categories": [{"id": 1, "name": "Technology"}],
     *     "tags": [{"id": 1, "name": "Laravel"}]
     *   }
     * }
     */
    public function edit(Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        return response()->json([
            'success' => true,
            'data' => [
                'post' => $post->load('author:id,name,username', 'categories:id,name', 'tags:id,name', 'media:id,file_name,file_path', 'county:id,name'),
                'categories' => Category::select('id', 'name')->orderBy('name')->get(),
                'tags' => Tag::select('id', 'name')->orderBy('name')->get(),
            ],
        ]);
    }

    /**
     * Update a post
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Update existing post details (editors/admins/owner)
     *
     * @urlParam post required The post ID
     * @bodyParam title string Post title. Example: Updated Title
     * @bodyParam body string Post content. Example: <p>Updated content</p>
     * @bodyParam slug string New slug (unique). Example: updated-title
     * @bodyParam excerpt string Post summary. Example: Updated summary
     * @bodyParam status string Post status. Example: published
     * @bodyParam category_ids array Category IDs. Example: [1, 2]
     * @bodyParam tag_ids array Tag IDs. Example: [1, 3]
     * @bodyParam meta_title string SEO title. Example: Updated - Our Blog
     * @bodyParam meta_description string SEO description. Example: Updated post
     * @bodyParam is_featured boolean Feature status. Example: false
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Post updated successfully",
     *   "data": {"id": 1, "title": "Updated Title"}
     * }
     */
    public function update(Request $request, Post $post): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'string|max:255',
            'body' => 'string',
            'slug' => 'string|max:255|unique:posts,slug,' . $post->id,
            'excerpt' => 'nullable|string|max:500',
            'status' => 'in:draft,published',
            'county_id' => 'nullable|exists:counties,id',
            'published_at' => 'nullable|date_format:Y-m-d\TH:i:sZ',
            'hero_image_path' => 'nullable|string|max:500',
            'category_ids' => 'array|exists:categories,id',
            'tag_ids' => 'array|exists:tags,id',
            'media_ids' => 'nullable|array|exists:media,id',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'is_featured' => 'boolean',
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

        // Sync media files if provided
        if (array_key_exists('media_ids', $validated)) {
            $post->media()->sync($validated['media_ids']);
            // Update media privacy based on post status
            $post->updateAttachedMediaPrivacy();
        }

        $this->logAuditAction('update', Post::class, $post->id, $oldValues, $validated, "Updated post: {$post->title}");

        return response()->json([
            'success' => true,
            'message' => 'Post updated successfully',
            'data' => $post->load('author:id,name,username', 'categories:id,name', 'tags:id,name', 'media:id,file_name,file_path,type'),
        ]);
    }

    /**
     * Delete a post
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Soft-delete a post (editors/admins/owner)
     *
     * @urlParam post required The post ID
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Post deleted successfully"
     * }
     */
    public function destroy(Post $post): JsonResponse
    {
        $this->logAuditAction('delete', Post::class, $post->id, $post->only(['title', 'slug']), null, "Deleted post: {$post->title}");
        $post->delete();

        return response()->json([
            'success' => true,
            'message' => 'Post deleted successfully',
        ]);
    }

    /**
     * Restore a soft-deleted post
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Restore deleted post (admins only)
     *
     * @urlParam post required The post ID
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Post restored successfully"
     * }
     */
    public function restore($postId): JsonResponse
    {
        $post = Post::withTrashed()->findOrFail($postId);
        $this->authorize('restore', $post);

        $post->restore();
        $this->logAuditAction('restore', Post::class, $post->id, null, $post->only(['title', 'slug']), "Restored post: {$post->title}");

        return response()->json([
            'success' => true,
            'message' => 'Post restored successfully',
            'data' => $post,
        ]);
    }

    /**
     * Force delete a post permanently
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Permanently delete post and associated data (admins only)
     *
     * @urlParam post required The post ID
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Post permanently deleted"
     * }
     */
    public function forceDelete($postId): JsonResponse
    {
        $post = Post::withTrashed()->findOrFail($postId);
        $this->authorize('forceDelete', $post);

        $postTitle = $post->title;
        $post->forceDelete();

        $this->logAuditAction('force_delete', Post::class, $post->id, null, null, "Permanently deleted post: {$postTitle}");

        return response()->json([
            'success' => true,
            'message' => 'Post permanently deleted',
        ]);
    }

    /**
     * Log audit actions
     */
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
