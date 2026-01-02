<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\Contracts\BlogTaxonomyServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @group Admin - Blog Tags
 */
class TagAdminController extends Controller
{
    protected BlogTaxonomyServiceContract $taxonomyService;

    public function __construct(BlogTaxonomyServiceContract $taxonomyService)
    {
        $this->taxonomyService = $taxonomyService;
        // Middleware is applied at route level - policies handle authorization
    }

    /**
     * List Tags
     * 
     * @group Admin - Blog Tags
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Tag::class);

            $filters = $request->only(['search']);
            $perPage = (int) $request->get('per_page', 50);

            $tags = $this->taxonomyService->listTags($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $tags->items(),
                'pagination' => [
                    'total' => $tags->total(),
                    'per_page' => $tags->perPage(),
                    'current_page' => $tags->currentPage(),
                    'last_page' => $tags->lastPage(),
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Exception $e) {
            Log::error('TagAdminController index failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve tags'], 500);
        }
    }

    /**
     * Metadata
     * 
     * @group Admin - Blog Tags
     * @authenticated
     */
    public function create(): JsonResponse
    {
        try {
            $this->authorize('create', Tag::class);
            return response()->json([
                'success' => true,
                'data' => ['message' => 'Ready to create new tag'],
            ]);
        } catch (\Exception $e) {
            Log::error('TagAdminController create failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to prepare creation'], 500);
        }
    }

    /**
     * Store Tag
     * 
     * @group Admin - Blog Tags
     * @authenticated
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $this->authorize('create', Tag::class);
            
            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:tags,name',
                'slug' => 'nullable|string|max:120|unique:tags,slug',
            ]);

            $tag = $this->taxonomyService->createTag($request->user(), $validated);

            return response()->json([
                'success' => true,
                'message' => 'Tag created successfully',
                'data' => $tag,
            ], 201);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('TagAdminController store failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create tag'], 500);
        }
    }

    /**
     * Show Tag
     * 
     * @group Admin - Blog Tags
     * @authenticated
     */
    public function show(Tag $tag): JsonResponse
    {
        try {
            $this->authorize('view', $tag);
            return response()->json([
                'success' => true,
                'data' => $tag->loadCount('posts'),
            ]);
        } catch (\Exception $e) {
            Log::error('TagAdminController show failed', ['error' => $e->getMessage(), 'id' => $tag->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve tag'], 500);
        }
    }

    /**
     * Edit Metadata
     * 
     * @group Admin - Blog Tags
     * @authenticated
     */
    public function edit(Tag $tag): JsonResponse
    {
        try {
            $this->authorize('update', $tag);
            return response()->json([
                'success' => true,
                'data' => ['tag' => $tag],
            ]);
        } catch (\Exception $e) {
            Log::error('TagAdminController edit failed', ['error' => $e->getMessage(), 'id' => $tag->id]);
            return response()->json(['success' => false, 'message' => 'Failed to prepare edit'], 500);
        }
    }

    /**
     * Update Tag
     * 
     * @group Admin - Blog Tags
     * @authenticated
     */
    public function update(Request $request, Tag $tag): JsonResponse
    {
        try {
            $this->authorize('update', $tag);
            
            $validated = $request->validate([
                'name' => 'string|max:100|unique:tags,name,' . $tag->id,
                'slug' => 'string|max:120|unique:tags,slug,' . $tag->id,
            ]);

            $updatedTag = $this->taxonomyService->updateTag($request->user(), $tag, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Tag updated successfully',
                'data' => $updatedTag,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('TagAdminController update failed', ['error' => $e->getMessage(), 'id' => $tag->id]);
            return response()->json(['success' => false, 'message' => 'Failed to update tag'], 500);
        }
    }

    /**
     * Delete Tag
     * 
     * @group Admin - Blog Tags
     * @authenticated
     */
    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        try {
            $this->authorize('delete', $tag);
            $this->taxonomyService->deleteTag($request->user(), $tag);

            return response()->json([
                'success' => true,
                'message' => 'Tag deleted successfully',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Exception $e) {
            Log::error('TagAdminController destroy failed', ['error' => $e->getMessage(), 'id' => $tag->id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete tag'], 500);
        }
    }

    /**
     * Affected Posts
     * 
     * List posts that will be affected if this tag is deleted.
     * 
     * @group Admin - Blog Tags
     * @authenticated
     */
    public function affectedPosts(Tag $tag): JsonResponse
    {
        try {
            $this->authorize('view', $tag);
            $perPage = (int) request('per_page', 15);
            $posts = $this->taxonomyService->getAffectedPostsByTag($tag, $perPage);

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
            Log::error('TagAdminController affectedPosts failed', ['error' => $e->getMessage(), 'id' => $tag->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve affected posts'], 500);
        }
    }
}
