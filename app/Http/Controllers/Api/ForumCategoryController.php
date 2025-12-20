<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ForumCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForumCategoryController extends Controller
{

    /**
     * List all active forum categories.
     * 
     * @group Forum Categories
     * @unauthenticated
     * 
     * @response {
     *  "data": [
     *    {
     *      "id": 1,
     *      "name": "General Discussion",
     *      "slug": "general-discussion",
     *      "description": "Talk about anything here.",
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
        $categories = ForumCategory::active()
            ->with(['children' => function ($query) {
                $query->active()->ordered()->withCount('threads');
            }])
            ->ordered()
            ->withCount('threads')
            ->get();

        return response()->json([
            'data' => $categories,
        ]);
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

        return response()->json([
            'data' => $categoryData,
        ]);
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
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ForumCategory::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:forum_categories,slug',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $category = ForumCategory::create($validated);

        return response()->json([
            'data' => $category,
            'message' => 'Category created successfully.',
        ], 201);
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
    public function update(Request $request, ForumCategory $category): JsonResponse
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:forum_categories,slug,' . $category->id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $category->update($validated);

        return response()->json([
            'data' => $category,
            'message' => 'Category updated successfully.',
        ]);
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
        $this->authorize('delete', $category);

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }
}
