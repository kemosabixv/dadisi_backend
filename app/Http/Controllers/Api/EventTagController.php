<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EventException;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventTagResource;
use App\Models\EventTag;
use App\Services\Contracts\EventTaxonomyServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * @group Admin - Event Tags
 */
class EventTagController extends Controller
{
    protected EventTaxonomyServiceContract $taxonomyService;

    public function __construct(EventTaxonomyServiceContract $taxonomyService)
    {
        $this->taxonomyService = $taxonomyService;
        $this->middleware(['auth:sanctum', 'admin'])->except(['index']);
    }

    /**
     * List All Tags
     * 
     * @group Event Tags
     */
    public function index(): JsonResponse
    {
        try {
            $tags = $this->taxonomyService->listTags();
            return response()->json(['success' => true, 'data' => EventTagResource::collection($tags)]);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('EventTagController index failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve event tags'], 500);
        }
    }

    /**
     * Create Tag
     * 
     * @group Admin - Event Tags
     * @authenticated
     */
    public function store(\App\Http\Requests\Api\StoreEventTagRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', EventTag::class);
            
            $tag = $this->taxonomyService->createTag($request->user(), $request->validated());
            return response()->json(['success' => true, 'data' => new EventTagResource($tag)], 201);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('EventTagController store failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create event tag'], 500);
        }
    }

    /**
     * Delete Tag
     * 
     * @group Admin - Event Tags
     * @authenticated
     */
    public function destroy(Request $request, EventTag $tag): JsonResponse
    {
        try {
            $this->authorize('delete', $tag);
            
            $this->taxonomyService->deleteTag($request->user(), $tag);
            return response()->json(['success' => true, 'message' => 'Event tag deleted successfully']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('EventTagController destroy failed', ['error' => $e->getMessage(), 'id' => $tag->id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete event tag'], 500);
        }
    }
}
