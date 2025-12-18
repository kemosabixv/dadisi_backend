<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TagAdminController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Tag::class, 'tag');
    }

    /**
     * Get Tag Creation Metadata
     *
     * Retrieves any necessary metadata or configuration required before creating a new tag.
     * Use this endpoint to check if the tag creation interface is ready (e.g., checking permission status).
     *
     * @group Blog Admin - Tags
     * @groupDescription Administrative endpoints for managing blog tags (micro-taxonomy). Tags are keyword descriptors attached to posts.
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {"message": "Ready to create new tag"}
     * }
     */
    public function create(): JsonResponse
    {
        $this->authorize('create', Tag::class);

        return response()->json([
            'success' => true,
            'data' => ['message' => 'Ready to create new tag'],
        ]);
    }

    /**
     * List Tags (Admin)
     *
     * Retrieves a paginated list of all blog tags.
     * The response includes the usage count (number of posts) for each tag.
     *
     * @group Blog Admin - Tags
     * @authenticated
     *
     * @queryParam search string optional Search tags by name. Example: tutorial
     * @queryParam per_page integer optional Pagination size. Example: 50
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {"id": 1, "name": "tutorial", "slug": "tutorial", "posts_count": 3}
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tag::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $tags = $query->withCount('posts')->latest()->paginate($request->per_page ?? 50);
        $response = array_merge($tags->toArray(), [
            'success' => true,
        ]);

        return response()->json($response);
    }

    /**
     * Create New Tag
     *
     * Registers a new tag in the system.
     * The slug is automatically generated from the name if omitted.
     *
     * @group Blog Admin - Tags
     * @authenticated
     *
     * @bodyParam name string required Unique name for the tag. Example: Laravel
     * @bodyParam slug string optional Unique URL-friendly slug. Auto-generated if empty. Example: laravel
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Tag created successfully",
     *   "data": {"id": 1, "name": "Laravel", "slug": "laravel"}
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:tags,name',
            'slug' => 'nullable|string|max:120|unique:tags,slug',
        ]);

        if (!isset($validated['slug'])) {
            $validated['slug'] = \Str::slug($validated['name']);
        }

        $tag = Tag::create($validated);

        $this->logAuditAction('create', Tag::class, $tag->id, null, $tag->only(['name', 'slug']), "Created tag: {$tag->name}");

        return response()->json([
            'success' => true,
            'message' => 'Tag created successfully',
            'data' => $tag,
        ], 201);
    }

    /**
     * Get Tag Details
     *
     * Retrieves detailed information about a specific tag, including the count of associated posts.
     *
     * @group Blog Admin - Tags
     * @authenticated
     *
     * @urlParam tag integer required The tag ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {"id": 1, "name": "Laravel", "slug": "laravel", "posts_count": 5}
     * }
     */
    public function show(Tag $tag): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $tag->withCount('posts'),
        ]);
    }

    /**
     * Get Tag Edit Metadata
     *
     * Retrieves the tag data formatted for the "Edit Tag" form.
     *
     * @group Blog Admin - Tags
     * @authenticated
     *
     * @urlParam tag integer required The tag ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "tag": {"id": 1, "name": "Laravel", "slug": "laravel"}
     *   }
     * }
     */
    public function edit(Tag $tag): JsonResponse
    {
        $this->authorize('update', $tag);

        return response()->json([
            'success' => true,
            'data' => ['tag' => $tag],
        ]);
    }

    /**
     * Update Tag
     *
     * Modifies the details of an existing tag.
     *
     * @group Blog Admin - Tags
     * @authenticated
     *
     * @urlParam tag integer required The tag ID. Example: 1
     * @bodyParam name string optional Update name (must be unique). Example: Updated Tag
     * @bodyParam slug string optional Update slug (must be unique). Example: updated-tag
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Tag updated successfully",
     *   "data": {"id": 1, "name": "Updated Tag"}
     * }
     */
    public function update(Request $request, Tag $tag): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:100|unique:tags,name,' . $tag->id,
            'slug' => 'string|max:120|unique:tags,slug,' . $tag->id,
        ]);

        $oldValues = $tag->only(array_keys($validated));
        $tag->update($validated);

        $this->logAuditAction('update', Tag::class, $tag->id, $oldValues, $validated, "Updated tag: {$tag->name}");

        return response()->json([
            'success' => true,
            'message' => 'Tag updated successfully',
            'data' => $tag,
        ]);
    }

    /**
     * Delete Tag
     *
     * Permanently removes a tag from the system.
     *
     * @group Blog Admin - Tags
     * @authenticated
     *
     * @urlParam tag integer required The tag ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Tag deleted successfully"
     * }
     */
    public function destroy(Tag $tag): JsonResponse
    {
        $this->logAuditAction('delete', Tag::class, $tag->id, $tag->only(['name', 'slug']), null, "Deleted tag: {$tag->name}");
        $tag->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tag deleted successfully',
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
