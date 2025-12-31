<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EventException;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventCategoryResource;
use App\Models\EventCategory;
use App\Services\Contracts\EventTaxonomyServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * @group Admin - Event Categories
 */
class EventCategoryController extends Controller
{
    protected EventTaxonomyServiceContract $taxonomyService;

    public function __construct(EventTaxonomyServiceContract $taxonomyService)
    {
        $this->taxonomyService = $taxonomyService;
        $this->middleware(['auth:sanctum', 'admin'])->except(['index', 'show']);
    }

    /**
     * List Categories
     * 
     * @group Event Categories
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'parent_only' => true,
                'active_only' => true,
            ];
            
            $categories = $this->taxonomyService->listCategories($filters);
            
            return response()->json([
                'success' => true, 
                'data' => EventCategoryResource::collection($categories)
            ]);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('EventCategoryController index failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve event categories'], 500);
        }
    }

    /**
     * Get Category Details
     * 
     * @group Event Categories
     */
    public function show(EventCategory $category): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => new EventCategoryResource($category->load('children'))
            ]);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('EventCategoryController show failed', ['error' => $e->getMessage(), 'id' => $category->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve event category'], 500);
        }
    }

    /**
     * Create Category
     * 
     * @group Admin - Event Categories
     * @authenticated
     */
    public function store(\App\Http\Requests\Api\StoreEventCategoryRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', EventCategory::class);

            $category = $this->taxonomyService->createCategory($request->user(), $request->validated());
            
            return response()->json([
                'success' => true,
                'data' => new EventCategoryResource($category)
            ], 201);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('EventCategoryController store failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create event category'], 500);
        }
    }

    /**
     * Update Category
     * 
     * @group Admin - Event Categories
     * @authenticated
     */
    public function update(\App\Http\Requests\Api\UpdateEventCategoryRequest $request, EventCategory $category): JsonResponse
    {
        try {
            $this->authorize('update', $category);

            $updatedCategory = $this->taxonomyService->updateCategory($request->user(), $category, $request->validated());
            
            return response()->json([
                'success' => true,
                'data' => new EventCategoryResource($updatedCategory)
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('EventCategoryController update failed', ['error' => $e->getMessage(), 'id' => $category->id]);
            return response()->json(['success' => false, 'message' => 'Failed to update event category'], 500);
        }
    }

    /**
     * Delete Category
     * 
     * @group Admin - Event Categories
     * @authenticated
     */
    public function destroy(Request $request, EventCategory $category): JsonResponse
    {
        try {
            $this->authorize('delete', $category);

            $this->taxonomyService->deleteCategory($request->user(), $category);
            
            return response()->json(['success' => true, 'message' => 'Category deleted successfully'], Response::HTTP_NO_CONTENT);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('EventCategoryController destroy failed', ['error' => $e->getMessage(), 'id' => $category->id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete event category'], 500);
        }
    }
}
