<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\AuditLog;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @group Blog Management - Author
 *
 * APIs for authors to manage their own blog posts.
 * Authors can create, update, delete, publish, and unpublish their own posts.
 * Unlike admin endpoints, these are scoped to the authenticated user's posts only.
 */
class AuthorPostController extends Controller
{
    /**
     * List Author's Posts
     *
     * Retrieves a paginated list of the authenticated user's posts.
     * Includes drafts, published, and trashed posts based on status filter.
     *
     * @authenticated
     *
     * @queryParam status string optional Filter by status (draft, published, trashed). Example: draft
     * @queryParam search string optional Search in title or body. Example: welcome
     * @queryParam per_page integer optional Items per page. Default: 15. Example: 10
     * @queryParam page integer optional Page number. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "My First Post",
     *       "slug": "my-first-post",
     *       "status": "draft",
     *       "excerpt": "An intro...",
     *       "created_at": "2025-01-01T00:00:00Z",
     *       "categories": [{"id": 1, "name": "Tech"}],
     *       "tags": [{"id": 1, "name": "Laravel"}]
     *     }
     *   ],
     *   "current_page": 1,
     *   "last_page": 5,
     *   "per_page": 15,
     *   "total": 75
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $query = Post::where('user_id', auth()->id())
            ->with('categories:id,name,slug', 'tags:id,name,slug', 'county:id,name');

        if ($request->status === 'trashed') {
            $query->onlyTrashed();
        } elseif ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $posts = $query->latest('created_at')->paginate($request->per_page ?? 15);

        return response()->json(array_merge($posts->toArray(), ['success' => true]));
    }

    /**
     * Get Post Creation Metadata
     *
     * Retrieves categories and tags for populating the create post form.
     *
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "categories": [{"id": 1, "name": "Technology", "slug": "technology"}],
     *     "tags": [{"id": 1, "name": "Laravel", "slug": "laravel"}]
     *   }
     * }
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => Category::select('id', 'name', 'slug')->orderBy('name')->get(),
                'tags' => Tag::select('id', 'name', 'slug')->orderBy('name')->get(),
            ],
        ]);
    }

    /**
     * Create New Post
     *
     * Creates a new blog post for the authenticated author.
     * The slug is auto-generated from the title if not provided.
     *
     * @authenticated
     *
     * @bodyParam title string required Post title (max 255 chars). Example: My First Post
     * @bodyParam content string required Post body content (HTML). Example: <p>Hello world!</p>
     * @bodyParam slug string optional Custom URL slug (auto-generated if omitted). Example: my-first-post
     * @bodyParam excerpt string optional Short summary (max 500 chars). Example: A brief intro
     * @bodyParam status string optional Publication status: draft or published. Default: draft. Example: draft
     * @bodyParam county_id integer optional County ID for regional context. Example: 1
     * @bodyParam category_ids array optional Array of category IDs. Example: [1, 2]
     * @bodyParam tag_ids array optional Array of tag IDs. Example: [1, 3]
     * @bodyParam hero_image_path string optional Path to hero image. Example: /uploads/hero.jpg
     * @bodyParam meta_title string optional SEO title (max 60 chars). Example: My First Post | Blog
     * @bodyParam meta_description string optional SEO description (max 160 chars). Example: A great post
     * @bodyParam is_featured boolean optional Mark as featured. Example: false
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Post created successfully",
     *   "data": {
     *     "id": 1,
     *     "title": "Sustainable Urban Farming",
     *     "slug": "sustainable-urban-farming",
     *     "status": "draft"
     *   }
     * }
     * @response 422 {
     *   "message": "The title field is required.",
     *   "errors": {"title": ["The title field is required."]}
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'slug' => 'nullable|string|max:255|unique:posts,slug',
            'excerpt' => 'nullable|string|max:500',
            'status' => 'in:draft,published',
            'county_id' => 'nullable|exists:counties,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'hero_image_path' => 'nullable|string|max:500',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'is_featured' => 'boolean',
        ]);

        // Map content to body for database storage
        $validated['body'] = $validated['content'];
        unset($validated['content']);

        // Generate slug from title if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
            // Ensure uniqueness
            $count = Post::where('slug', 'like', $validated['slug'] . '%')->count();
            if ($count > 0) {
                $validated['slug'] .= '-' . ($count + 1);
            }
        }

        $validated['user_id'] = auth()->id();
        $validated['status'] = $validated['status'] ?? 'draft';

        // Set published_at if publishing
        if ($validated['status'] === 'published') {
            $validated['published_at'] = now();
        }

        $post = Post::create($validated);

        // Attach categories and tags
        if (!empty($validated['category_ids'])) {
            $post->categories()->sync($validated['category_ids']);
        }
        if (!empty($validated['tag_ids'])) {
            $post->tags()->sync($validated['tag_ids']);
        }

        $this->logAuditAction('create', Post::class, $post->id, null, $post->only(['title', 'slug', 'status']), "Author created post: {$post->title}");

        // Mark hero image as permanent (clear temporary_until)
        if (!empty($validated['hero_image_path'])) {
            $this->markMediaAsPermanent($validated['hero_image_path']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => $post->load('categories:id,name,slug', 'tags:id,name,slug'),
        ], 201);
    }

    /**
     * Get Post Details
     *
     * Retrieves the full details of a specific post owned by the author.
     *
     * @authenticated
     *
     * @urlParam post integer required The post ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "title": "My First Post",
     *     "slug": "my-first-post",
     *     "body": "<p>Content...</p>",
     *     "status": "draft",
     *     "categories": [{"id": 1, "name": "Tech"}],
     *     "tags": [{"id": 1, "name": "Laravel"}]
     *   }
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "You do not have permission to view this post."
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Post] 999"
     * }
     */
    public function show(Post $post): JsonResponse
    {
        // Ensure the user owns this post
        if ($post->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this post.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $post->load('categories:id,name,slug', 'tags:id,name,slug', 'county:id,name', 'media'),
        ]);
    }

    /**
     * Get Post Edit Metadata
     *
     * Retrieves the post data along with all categories and tags for the edit form.
     *
     * @authenticated
     *
     * @urlParam post integer required The post ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "post": {"id": 1, "title": "My Post", "body": "..."},
     *     "categories": [{"id": 1, "name": "Technology"}],
     *     "tags": [{"id": 1, "name": "Laravel"}]
     *   }
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "You do not have permission to edit this post."
     * }
     */
    public function edit(Post $post): JsonResponse
    {
        // Ensure the user owns this post
        if ($post->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to edit this post.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'post' => $post->load('categories:id,name,slug', 'tags:id,name,slug', 'county:id,name', 'media'),
                'categories' => Category::select('id', 'name', 'slug')->orderBy('name')->get(),
                'tags' => Tag::select('id', 'name', 'slug')->orderBy('name')->get(),
            ],
        ]);
    }

    /**
     * Update Post
     *
     * Updates an existing post owned by the author.
     * All fields are optional for partial updates.
     *
     * @authenticated
     *
     * @urlParam post integer required The post ID. Example: 1
     * @bodyParam title string optional Post title. Example: Updated Title
     * @bodyParam content string optional Post body (HTML). Example: <p>Updated content</p>
     * @bodyParam slug string optional URL slug. Example: updated-title
     * @bodyParam excerpt string optional Short summary. Example: Updated summary
     * @bodyParam status string optional draft or published. Example: published
     * @bodyParam county_id integer optional County ID. Example: 2
     * @bodyParam category_ids array optional Category IDs. Example: [1, 2]
     * @bodyParam tag_ids array optional Tag IDs. Example: [1, 3]
     * @bodyParam hero_image_path string optional Hero image path. Example: /uploads/new-hero.jpg
     * @bodyParam meta_title string optional SEO title. Example: Updated | Blog
     * @bodyParam meta_description string optional SEO description. Example: Updated post
     * @bodyParam is_featured boolean optional Featured status. Example: true
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Post updated successfully",
     *   "data": {"id": 1, "title": "Updated Title", "status": "published"}
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "You do not have permission to update this post."
     * }
     */
    public function update(Request $request, Post $post): JsonResponse
    {
        // Ensure the user owns this post
        if ($post->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this post.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'string|max:255',
            'content' => 'string',
            'slug' => 'string|max:255|unique:posts,slug,' . $post->id,
            'excerpt' => 'nullable|string|max:500',
            'status' => 'in:draft,published',
            'county_id' => 'nullable|exists:counties,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'hero_image_path' => 'nullable|string|max:500',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'is_featured' => 'boolean',
        ]);

        // Map content to body
        if (isset($validated['content'])) {
            $validated['body'] = $validated['content'];
            unset($validated['content']);
        }

        // Set published_at if publishing for the first time
        if (isset($validated['status']) && $validated['status'] === 'published' && $post->status !== 'published') {
            $validated['published_at'] = now();
        }

        $oldValues = $post->only(array_keys($validated));
        $post->update($validated);

        // Sync categories and tags
        if (isset($validated['category_ids'])) {
            $post->categories()->sync($validated['category_ids']);
        }
        if (isset($validated['tag_ids'])) {
            $post->tags()->sync($validated['tag_ids']);
        }

        $this->logAuditAction('update', Post::class, $post->id, $oldValues, $validated, "Author updated post: {$post->title}");

        // Mark hero image as permanent (clear temporary_until)
        if (!empty($validated['hero_image_path'])) {
            $this->markMediaAsPermanent($validated['hero_image_path']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Post updated successfully',
            'data' => $post->fresh()->load('categories:id,name,slug', 'tags:id,name,slug'),
        ]);
    }

    /**
     * Delete Post (Soft)
     *
     * Moves the author's post to trash. Can be restored later.
     *
     * @authenticated
     *
     * @urlParam post integer required The post ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Post moved to trash"
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "You do not have permission to delete this post."
     * }
     */
    public function destroy(Post $post): JsonResponse
    {
        // Ensure the user owns this post
        if ($post->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this post.',
            ], 403);
        }

        $this->logAuditAction('delete', Post::class, $post->id, $post->only(['title', 'slug']), null, "Author trashed post: {$post->title}");
        $post->delete();

        return response()->json([
            'success' => true,
            'message' => 'Post moved to trash',
        ]);
    }

    /**
     * Restore Deleted Post
     *
     * Restores a soft-deleted post from the trash.
     *
     * @authenticated
     *
     * @urlParam post integer required The trashed post ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Post restored successfully",
     *   "data": {"id": 1, "title": "My Post", "status": "draft"}
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "You do not have permission to restore this post."
     * }
     */
    public function restore($postId): JsonResponse
    {
        $post = Post::withTrashed()->findOrFail($postId);

        // Ensure the user owns this post
        if ($post->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to restore this post.',
            ], 403);
        }

        $post->restore();
        $this->logAuditAction('restore', Post::class, $post->id, null, $post->only(['title', 'slug']), "Author restored post: {$post->title}");

        return response()->json([
            'success' => true,
            'message' => 'Post restored successfully',
            'data' => $post,
        ]);
    }

    /**
     * Publish Post
     *
     * Changes the post status from draft to published.
     * Sets the published_at timestamp if not already set.
     *
     * @authenticated
     *
     * @urlParam post integer required The post ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Post published successfully",
     *   "data": {"id": 1, "title": "My Post", "status": "published", "published_at": "2025-01-01T00:00:00Z"}
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "You do not have permission to publish this post."
     * }
     * @response 400 {
     *   "success": false,
     *   "message": "Post is already published."
     * }
     */
    public function publish(Post $post): JsonResponse
    {
        // Ensure the user owns this post
        if ($post->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to publish this post.',
            ], 403);
        }

        if ($post->status === 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Post is already published.',
            ], 400);
        }

        $oldStatus = $post->status;
        $post->update([
            'status' => 'published',
            'published_at' => $post->published_at ?? now(),
        ]);

        $this->logAuditAction('publish', Post::class, $post->id, ['status' => $oldStatus], ['status' => 'published'], "Author published post: {$post->title}");

        return response()->json([
            'success' => true,
            'message' => 'Post published successfully',
            'data' => $post->fresh(),
        ]);
    }

    /**
     * Unpublish Post
     *
     * Changes the post status from published back to draft.
     * The post will no longer be visible to the public.
     *
     * @authenticated
     *
     * @urlParam post integer required The post ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Post unpublished successfully",
     *   "data": {"id": 1, "title": "My Post", "status": "draft"}
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "You do not have permission to unpublish this post."
     * }
     * @response 400 {
     *   "success": false,
     *   "message": "Post is not published."
     * }
     */
    public function unpublish(Post $post): JsonResponse
    {
        // Ensure the user owns this post
        if ($post->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to unpublish this post.',
            ], 403);
        }

        if ($post->status !== 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Post is not published.',
            ], 400);
        }

        $post->update(['status' => 'draft']);

        $this->logAuditAction('unpublish', Post::class, $post->id, ['status' => 'published'], ['status' => 'draft'], "Author unpublished post: {$post->title}");

        return response()->json([
            'success' => true,
            'message' => 'Post unpublished successfully',
            'data' => $post->fresh(),
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

    /**
     * Mark media as permanent by clearing the temporary_until field.
     * Extracts media path from URL and finds matching record.
     */
    private function markMediaAsPermanent(string $heroImagePath): void
    {
        try {
            // Extract file path from URL (e.g., /storage/media/2025-12/image.jpg -> /media/2025-12/image.jpg)
            $filePath = preg_replace('#^.*/storage#', '', $heroImagePath);
            
            Media::where('file_path', $filePath)
                ->whereNotNull('temporary_until')
                ->update(['temporary_until' => null]);
        } catch (\Exception $e) {
            Log::error('Failed to mark media as permanent', ['error' => $e->getMessage()]);
        }
    }
}
