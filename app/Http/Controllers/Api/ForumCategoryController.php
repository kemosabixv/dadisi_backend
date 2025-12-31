<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreForumCategoryRequest;
use App\Http\Requests\Api\UpdateForumCategoryRequest;
use App\Models\ForumCategory;
use App\Services\Contracts\ForumTaxonomyServiceContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ForumCategoryController extends Controller
{
    public function __construct(private ForumTaxonomyServiceContract $taxonomyService)
    {
    }

    /**
     * List all active forum categories.
     * 
     * @group Forum Categories
     * @unauthenticated
     * 
     * @response 200 {
     *  "data": [
     *    {
     *      "id": 1,
     *      "name": "General Discussion",
     *      "slug": "general-discussion",
     *      "description": "Talk about lab life, science, and anything community related.",
     *      "icon": "message-circle",
     *      "color": "#3b82f6",
     *      "threads_count": 5,
     *      "posts_count": 23,
     *      "order": 0,
     *      "is_active": true
     *    }
     *  ]
     * }
     */
    public function index(): JsonResponse
    {
        try {
            $categories = ForumCategory::active()
                ->with(['children' => function ($query) {
                    $query->active()->ordered()->withCount('threads');
                }])
                ->ordered()
                ->withCount('threads')
                ->get();

            return response()->json(['success' => true, 'data' => $categories]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve forum categories', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve forum categories'], 500);
        }
    }


    /**
     * Show a single category.
     * 
     * Returns a category with its pinned and recent threads.
     * 
     * @group Forum Categories
     * @unauthenticated
     * @urlParam category string required The slug of the category. Example: general-discussion
     */
    public function show(ForumCategory $category): JsonResponse
    {
        try {
            $category->loadCount('threads');
            $category->load(['children' => function ($query) {
                $query->active()->ordered()->withCount('threads');
            }]);

            $threads = $category->threads()
                ->with(['user:id,username,profile_picture_path', 'county:id,name', 'lastPost.user:id,username'])
                ->pinnedFirst()
                ->paginate(20);

            // Return format frontend expects: { data: { ...category, threads: [] } }
            $categoryData = $category->toArray();
            $categoryData['threads'] = $threads->items();
            $categoryData['threads_meta'] = [
                'current_page' => $threads->currentPage(),
                'last_page' => $threads->lastPage(),
                'total' => $threads->total(),
            ];

            return response()->json(['success' => true, 'data' => $categoryData]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve forum category', ['error' => $e->getMessage(), 'category_id' => $category->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve forum category'], 500);
        }
    }


    /**
     * Create a new category.
     * 
     * Admin only.
     * 
     * @group Forum Categories
     * @authenticated
     * 
     * @bodyParam name string required The name of the category. Example: Announcements
     * @bodyParam slug string required The unique slug for the category. Example: announcements
     * @bodyParam description string The description of the category. Example: Official updates.
     * @bodyParam icon string The icon name from Lucide (kebab-case). Example: megaphone
     * @bodyParam order integer The sort order (lower is first). Example: 0
     * @bodyParam is_active boolean Whether the category is visible. Example: true
     */
    public function store(StoreForumCategoryRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', ForumCategory::class);

            $validated = $request->validated();

            $category = ForumCategory::create($validated);

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => 'Category created successfully.',
            ], 201);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to create categories.'], 403);
        } catch (\Exception $e) {
            Log::error('Failed to create forum category', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create forum category'], 500);
        }
    }


    /**
     * Update a category.
     * 
     * Admin only.
     * 
     * @group Forum Categories
     * @authenticated
     * @urlParam category string required The slug of the category. Example: general-discussion
     * 
     * @bodyParam name string required The name of the category. Example: Announcements
     * @bodyParam slug string required The unique slug for the category. Example: announcements
     * @bodyParam description string The description of the category. Example: Official updates.
     * @bodyParam icon string The icon name from Lucide (kebab-case). Example: megaphone
     * @bodyParam order integer The sort order (lower is first). Example: 0
     * @bodyParam is_active boolean Whether the category is visible. Example: true
     */
    public function update(UpdateForumCategoryRequest $request, ForumCategory $category): JsonResponse
    {
        try {
            $this->authorize('update', $category);

            $validated = $request->validated();

            $category->update($validated);

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => 'Category updated successfully.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to update this category.'], 403);
        } catch (\Exception $e) {
            Log::error('Failed to update forum category', ['error' => $e->getMessage(), 'category_id' => $category->id]);
            return response()->json(['success' => false, 'message' => 'Failed to update forum category'], 500);
        }
    }


    /**
     * Delete a category.
     * 
     * Admin only.
     * 
     * @group Forum Categories
     * @authenticated
     * @urlParam category string required The slug of the category. Example: general-discussion
     */
    public function destroy(ForumCategory $category): JsonResponse
    {
        try {
            $this->authorize('delete', $category);

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to delete this category.'], 403);
        } catch (\Exception $e) {
            Log::error('Failed to delete forum category', ['error' => $e->getMessage(), 'category_id' => $category->id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete forum category'], 500);
        }
    }
}
