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
     * Get Post Creation Metadata
     *
     * Retrieves the necessary metadata (tags, categories) to populate the "Create Post" form.
     * This ensures the frontend has the latest taxonomy data before a user starts writing.
     *
     * @group Blog Management - Admin
     * @groupDescription Administrative endpoints for creating, editing, and managing blog posts. Includes status management (draft/published), SEO metadata, and soft deletion.
     * @authenticated
     *
     * @response 200 {
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
     * List Posts (Admin)
     *
     * Retrieves a paginated list of all posts with advanced filtering capabilities.
     * Unlike the public API, this endpoint returns draft and hidden posts for administrative review.
     *
     * @group Blog Management - Admin
     * @authenticated
     *
     * @queryParam status string optional Filter by publication status (draft, published). Example: published
     * @queryParam county_id integer optional Filter by County ID. Example: 1
     * @queryParam author_id integer optional Filter by Author ID. Example: 5
     * @queryParam search string optional Keyword search in title or body content. Example: welcome
     * @queryParam per_page integer optional Number of records per page. Example: 15
     * @queryParam page integer optional Page number. Example: 1
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
        
        $query = Post::with('author:id,username', 'categories', 'tags', 'county:id,name');

        if ($request->status === 'trashed') {
            $query->onlyTrashed();
        } elseif ($request->has('status')) {
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
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $posts = $query->latest('created_at')->paginate($request->per_page ?? 15);
        $response = array_merge($posts->toArray(), [
            'success' => true,
        ]);

        return response()->json($response);
    }

    /**
     * Create New Post
     *
     * Creates a new blog post entry.
     * Supports rich content (HTML), SEO metadata fields, and associations (categories, tags, media).
     * The slug is automatically generated from the title if not provided.
     *
     * @group Blog Management - Admin
     * @authenticated
     *
     * @bodyParam title string required The main headline of the post. Example: Welcome to Our Blog
     * @bodyParam content string required The full HTML content of the post. Example: <p>Post content here</p>
     * @bodyParam slug string optional Custom URL slug (must be unique). Auto-generated if omitted. Example: welcome-to-blog
     * @bodyParam excerpt string optional Short summary or teaser text (max 500 chars). Example: A brief introduction
     * @bodyParam status string optional Publication status: 'draft' or 'published'. Default: draft
     * @bodyParam county_id integer optional Regional context for the post. Example: 1
     * @bodyParam category_ids array optional List of category IDs to associate. Example: [1, 2]
     * @bodyParam tag_ids array optional List of tag IDs to associate. Example: [1, 3, 5]
     * @bodyParam meta_title string optional Custom page title for SEO (max 60 chars). Example: Welcome - Our Blog
     * @bodyParam meta_description string optional Meta description for SEO (max 160 chars). Example: A great blog post
     * @bodyParam is_featured boolean optional Mark this post as featured (pinned). Example: true
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
            'data' => $post->load('author:id,username', 'categories', 'tags', 'media'),
        ], 201);
    }

    /**
     * Get Post Details (Admin)
     *
     * Retrieves the full administrative details of a specific post.
     * Includes all metadata, SEO settings, and author information necessary for the edit view.
     *
     * @group Blog Management - Admin
     * @authenticated
     *
     * @urlParam post integer required The unique ID of the post. Example: 1
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
            'data' => $post->load('author:id,username', 'categories', 'tags', 'media', 'county:id,name'),
        ]);
    }

    /**
     * Get Post Edit Metadata
     *
     * Retrieves the post data along with all available categories and tags.
     * This "all-in-one" endpoint is designed to hydrate the "Edit Post" form in a single request.
     *
     * @group Blog Management - Admin
     * @authenticated
     *
     * @urlParam post integer required The unique ID of the post to edit. Example: 1
     *
     * @response 200 {
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
                'post' => $post->load('author:id,username', 'categories:id,name', 'tags:id,name', 'media:id,file_name,file_path', 'county:id,name'),
                'categories' => Category::select('id', 'name')->orderBy('name')->get(),
                'tags' => Tag::select('id', 'name')->orderBy('name')->get(),
            ],
        ]);
    }

    /**
     * Update Post
     *
     * Updates an existing blog post.
     * All fields are optional, allowing for partial updates (e.g., just changing the status).
     *
     * @group Blog Management - Admin
     * @authenticated
     *
     * @urlParam post integer required The unique ID of the post. Example: 1
     * @bodyParam title string optional Post title. Example: Updated Title
     * @bodyParam body string optional Post content (HTML). Example: <p>Updated content</p>
     * @bodyParam slug string optional Unique URL slug. Example: updated-title
     * @bodyParam excerpt string optional Short summary. Example: Updated summary
     * @bodyParam status string optional 'draft' or 'published'. Example: published
     * @bodyParam category_ids array optional Categories to sync. Example: [1, 2]
     * @bodyParam tag_ids array optional Tags to sync. Example: [1, 3]
     * @bodyParam meta_title string optional SEO title. Example: Updated - Our Blog
     * @bodyParam meta_description string optional SEO description. Example: Updated post
     * @bodyParam is_featured boolean optional Feature status. Example: false
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
            'data' => $post->load('author:id,username', 'categories:id,name', 'tags:id,name', 'media:id,file_name,file_path,type'),
        ]);
    }

    /**
     * Delete Post (Soft)
     *
     * Moves a post to the 'trashed' state.
     * Soft-deleted posts are not visible to the public but can be restored by an admin.
     *
     * @group Blog Management - Admin
     * @authenticated
     *
     * @urlParam post integer required The ID of the post to trash. Example: 1
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
     * Restore Deleted Post
     *
     * Recovers a soft-deleted post from the trash.
     * The post will regain its original status (e.g., draft or published).
     *
     * @group Blog Management - Admin
     * @authenticated
     *
     * @urlParam post integer required The ID of the trashed post. Example: 1
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
     * Permanently Delete Post
     *
     * Irreversibly purges a post and its associations from the database.
     * **Warning:** TThis action cannot be undone.
     *
     * @group Blog Management - Admin
     * @authenticated
     *
     * @urlParam post integer required The ID of the post to destroy. Example: 1
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
