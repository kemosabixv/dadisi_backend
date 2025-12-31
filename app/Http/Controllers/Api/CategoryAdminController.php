<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Contracts\BlogTaxonomyServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @group Admin - Blog Categories
 */
class CategoryAdminController extends Controller
{
    protected BlogTaxonomyServiceContract $taxonomyService;

    public function __construct(BlogTaxonomyServiceContract $taxonomyService)
    {
        $this->taxonomyService = $taxonomyService;
        // Middleware is applied at route level - policies handle authorization
    }

    /**
     * List Categories
     * 
     * @group Admin - Blog Categories
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Category::class);

            $filters = $request->only(['search']);
            $perPage = (int) $request->get('per_page', 20);

            $categories = $this->taxonomyService->listCategories($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $categories->items(),
                'pagination' => [
                    'total' => $categories->total(),
                    'per_page' => $categories->perPage(),
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Exception $e) {
            Log::error('CategoryAdminController index failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve categories'], 500);
        }
    }

    /**
     * Create Metadata
     * 
     * @group Admin - Blog Categories
     * @authenticated
     */
    public function create(): JsonResponse
    {
        try {
            $this->authorize('create', Category::class);
            return response()->json([
                'success' => true,
                'data' => ['message' => 'Ready to create new category'],
            ]);
        } catch (\Exception $e) {
            Log::error('CategoryAdminController create failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to prepare creation'], 500);
        }
    }

    /**
     * Store Category
     * 
     * @group Admin - Blog Categories
     * @authenticated
     */
    public function store(\App\Http\Requests\Api\StoreCategoryRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Category::class);
            $category = $this->taxonomyService->createCategory($request->user(), $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => $category,
            ], 201);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Exception $e) {
            Log::error('CategoryAdminController store failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create category'], 500);
        }
    }

    /**
     * Show Category
     * 
     * @group Admin - Blog Categories
     * @authenticated
     */
    public function show(Category $category): JsonResponse
    {
        try {
            $this->authorize('view', $category);
            return response()->json([
                'success' => true,
                'data' => $category,
            ]);
        } catch (\Exception $e) {
            Log::error('CategoryAdminController show failed', ['error' => $e->getMessage(), 'id' => $category->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve category'], 500);
        }
    }

    /**
     * Edit Metadata
     * 
     * @group Admin - Blog Categories
     * @authenticated
     */
    public function edit(Category $category): JsonResponse
    {
        try {
            $this->authorize('update', $category);
            return response()->json([
                'success' => true,
                'data' => ['category' => $category],
            ]);
        } catch (\Exception $e) {
            Log::error('CategoryAdminController edit failed', ['error' => $e->getMessage(), 'id' => $category->id]);
            return response()->json(['success' => false, 'message' => 'Failed to prepare edit'], 500);
        }
    }

    /**
     * Update Category
     * 
     * @group Admin - Blog Categories
     * @authenticated
     */
    public function update(\App\Http\Requests\Api\UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        try {
            $this->authorize('update', $category);
            $updatedCategory = $this->taxonomyService->updateCategory($request->user(), $category, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully',
                'data' => $updatedCategory,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Exception $e) {
            Log::error('CategoryAdminController update failed', ['error' => $e->getMessage(), 'id' => $category->id]);
            return response()->json(['success' => false, 'message' => 'Failed to update category'], 500);
        }
    }

    /**
     * Delete Category
     * 
     * @group Admin - Blog Categories
     * @authenticated
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        try {
            $this->authorize('delete', $category);
            $this->taxonomyService->deleteCategory($request->user(), $category);

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Exception $e) {
            Log::error('CategoryAdminController destroy failed', ['error' => $e->getMessage(), 'id' => $category->id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete category'], 500);
        }
    }
}
