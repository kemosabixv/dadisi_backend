<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreForumTagRequest;
use App\Http\Requests\Api\UpdateForumTagRequest;
use App\Models\ForumTag;
use App\Models\ForumThread;
use App\Services\Contracts\ForumTaxonomyServiceContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ForumTagController extends Controller
{
    public function __construct(private ForumTaxonomyServiceContract $taxonomyService)
    {
    }

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
        try {
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

            return response()->json(['success' => true, 'data' => $tags]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve forum tags', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve forum tags'], 500);
        }
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
        try {
            $threads = $tag->threads()
                ->with(['user:id,username,profile_picture_path', 'category:id,name,slug'])
                ->orderByDesc('created_at')
                ->paginate($request->get('per_page', 15));

            return response()->json(['success' => true, 'data' => ['tag' => $tag, 'threads' => $threads]]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve tag with threads', ['error' => $e->getMessage(), 'tag_id' => $tag->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve tag'], 500);
        }
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
    public function store(StoreForumTagRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', ForumTag::class);

            $validated = $request->validated();

            $tag = ForumTag::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'color' => $validated['color'] ?? '#6366f1',
                'description' => $validated['description'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data' => $tag,
                'message' => 'Tag created successfully.',
            ], 201);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to create tags.'], 403);
        } catch (\Exception $e) {
            Log::error('Failed to create forum tag', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create forum tag'], 500);
        }
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
    public function update(UpdateForumTagRequest $request, ForumTag $tag): JsonResponse
    {
        try {
            $this->authorize('update', $tag);

            $validated = $request->validated();

            if (isset($validated['name'])) {
                $validated['slug'] = Str::slug($validated['name']);
            }

            $tag->update($validated);

            return response()->json([
                'success' => true,
                'data' => $tag,
                'message' => 'Tag updated successfully.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to update this tag.'], 403);
        } catch (\Exception $e) {
            Log::error('Failed to update forum tag', ['error' => $e->getMessage(), 'tag_id' => $tag->id]);
            return response()->json(['success' => false, 'message' => 'Failed to update forum tag'], 500);
        }
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
        try {
            $this->authorize('delete', $tag);

            $tag->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tag deleted successfully.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to delete this tag.'], 403);
        } catch (\Exception $e) {
            Log::error('Failed to delete forum tag', ['error' => $e->getMessage(), 'tag_id' => $tag->id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete forum tag'], 500);
        }
    }
}
