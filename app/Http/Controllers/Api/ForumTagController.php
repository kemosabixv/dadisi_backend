<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ForumTag;
use App\Models\ForumThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ForumTagController extends Controller
{
    /**
     * List all tags with usage counts.
     *
     * @group Forum Tags
     * @unauthenticated
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Biotech",
     *       "slug": "biotech",
     *       "color": "#6366f1",
     *       "usage_count": 15
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $query = ForumTag::query();

        // Optional search
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sort by usage count by default, or by name
        $sortBy = $request->get('sort', 'usage_count');
        $sortDir = $request->get('order', 'desc');
        
        if ($sortBy === 'name') {
            $query->orderBy('name', $sortDir);
        } else {
            $query->orderByDesc('usage_count');
        }

        $tags = $query->get();

        return response()->json([
            'data' => $tags,
        ]);
    }

    /**
     * Show a tag with its threads.
     * 
     * @group Forum Tags
     * @unauthenticated
     * @urlParam tag string required The slug of the tag.
     */
    public function show(ForumTag $tag, Request $request): JsonResponse
    {
        $threads = $tag->threads()
            ->with(['user:id,username,profile_picture_path', 'category:id,name,slug'])
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'tag' => $tag,
            'threads' => $threads,
        ]);
    }

    /**
     * Create a new tag (admin only).
     * 
     * @group Forum Tags
     * @authenticated
     * 
     * @bodyParam name string required The name of the tag.
     * @bodyParam color string optional Hex color code (e.g., #6366f1).
     * @bodyParam description string optional Description of the tag.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ForumTag::class);

        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:forum_tags,name',
            'color' => 'nullable|string|max:7|regex:/^#[A-Fa-f0-9]{6}$/',
            'description' => 'nullable|string|max:255',
        ]);

        $tag = ForumTag::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'color' => $validated['color'] ?? '#6366f1',
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'data' => $tag,
            'message' => 'Tag created successfully.',
        ], 201);
    }

    /**
     * Update a tag (admin only).
     * 
     * @group Forum Tags
     * @authenticated
     * @urlParam tag string required The slug of the tag.
     * 
     * @bodyParam name string optional The name of the tag.
     * @bodyParam color string optional Hex color code.
     * @bodyParam description string optional Description of the tag.
     */
    public function update(Request $request, ForumTag $tag): JsonResponse
    {
        $this->authorize('update', $tag);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:50|unique:forum_tags,name,' . $tag->id,
            'color' => 'nullable|string|max:7|regex:/^#[A-Fa-f0-9]{6}$/',
            'description' => 'nullable|string|max:255',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $tag->update($validated);

        return response()->json([
            'data' => $tag,
            'message' => 'Tag updated successfully.',
        ]);
    }

    /**
     * Delete a tag (admin only).
     * 
     * @group Forum Tags
     * @authenticated
     * @urlParam tag string required The slug of the tag.
     */
    public function destroy(ForumTag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);

        $tag->delete();

        return response()->json([
            'message' => 'Tag deleted successfully.',
        ]);
    }
}
