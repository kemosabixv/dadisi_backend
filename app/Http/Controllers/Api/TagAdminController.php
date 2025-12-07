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
     * Show form data for creating a new tag
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Get form metadata needed to create a tag
     *
     * @response {
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
     * Display all tags
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description List all blog tags
     *
     * @queryParam search Search tags. Example: tutorial
     * @queryParam per_page Pagination size. Example: 50
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {"id": 1, "name": "tutorial", "slug": "tutorial", "post_count": 3}
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

        return response()->json([
            'success' => true,
            'data' => $tags->items(),
            'pagination' => [
                'total' => $tags->total(),
                'per_page' => $tags->perPage(),
                'current_page' => $tags->currentPage(),
            ],
        ]);
    }

    /**
     * Create a new tag
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Create new blog tag (editors/admins/premium members)
     *
     * @bodyParam name string required Tag name. Example: Laravel
     * @bodyParam slug string Tag slug (auto-generated if omitted). Example: laravel
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
     * Display a specific tag
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Get tag details with post count
     *
     * @urlParam tag required The tag ID
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
     * Show edit form data for a tag
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Get tag data for editing
     *
     * @urlParam tag integer required The tag ID
     *
     * @response {
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
     * Update a tag
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Update tag (editors/admins only)
     *
     * @urlParam tag required The tag ID
     * @bodyParam name string Tag name. Example: Updated Tag
     * @bodyParam slug string Tag slug. Example: updated-tag
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
     * Delete a tag
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Delete tag (editors/admins only)
     *
     * @urlParam tag required The tag ID
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
