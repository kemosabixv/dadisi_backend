<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CategoryAdminController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Category::class, 'category');
    }

    /**
     * Show form data for creating a new category
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Get form metadata needed to create a category
     *
     * @response {
     *   "success": true,
     *   "data": {"message": "Ready to create new category"}
     * }
     */
    public function create(): JsonResponse
    {
        $this->authorize('create', Category::class);

        return response()->json([
            'success' => true,
            'data' => ['message' => 'Ready to create new category'],
        ]);
    }

    /**
     * Display all categories
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description List all blog post categories (public accessible)
     *
     * @queryParam search Search categories. Example: news
     * @queryParam per_page Pagination size. Example: 20
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {"id": 1, "name": "News", "slug": "news", "description": "News and updates"}
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        $categories = $query->latest()->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $categories->items(),
            'pagination' => [
                'total' => $categories->total(),
                'per_page' => $categories->perPage(),
                'current_page' => $categories->currentPage(),
            ],
        ]);
    }

    /**
     * Create a new category
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Create new blog category (editors/admins/premium members)
     *
     * @bodyParam name string required Category name. Example: Technology
     * @bodyParam slug string Category slug (auto-generated if omitted). Example: technology
     * @bodyParam description string Category description. Example: Technology news and updates
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Category created successfully",
     *   "data": {"id": 1, "name": "Technology", "slug": "technology"}
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name',
            'slug' => 'nullable|string|max:120|unique:categories,slug',
            'description' => 'nullable|string|max:500',
        ]);

        if (!isset($validated['slug'])) {
            $validated['slug'] = \Str::slug($validated['name']);
        }

        $validated['created_by'] = auth()->id();

        $category = Category::create($validated);

        $this->logAuditAction('create', Category::class, $category->id, null, $category->only(['name', 'slug']), "Created category: {$category->name}");

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }

    /**
     * Display a specific category
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Get category details with post count
     *
     * @urlParam category required The category ID
     *
     * @response 200 {
     *   "success": true,
     *   "data": {"id": 1, "name": "News", "slug": "news", "post_count": 5}
     * }
     */
    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    /**
     * Show edit form data for a category
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Get category data for editing
     *
     * @urlParam category integer required The category ID
     *
     * @response {
     *   "success": true,
     *   "data": {
     *     "category": {"id": 1, "name": "Technology", "slug": "technology"}
     *   }
     * }
     */
    public function edit(Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        return response()->json([
            'success' => true,
            'data' => ['category' => $category],
        ]);
    }

    /**
     * Update a category
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Update category (creator or admin only)
     *
     * @urlParam category required The category ID
     * @bodyParam name string Category name. Example: Updated Category
     * @bodyParam slug string Category slug. Example: updated-category
     * @bodyParam description string Category description. Example: Updated description
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Category updated successfully",
     *   "data": {"id": 1, "name": "Updated Category"}
     * }
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:100|unique:categories,name,' . $category->id,
            'slug' => 'string|max:120|unique:categories,slug,' . $category->id,
            'description' => 'nullable|string|max:500',
        ]);

        $oldValues = $category->only(array_keys($validated));
        $category->update($validated);

        $this->logAuditAction('update', Category::class, $category->id, $oldValues, $validated, "Updated category: {$category->name}");

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category,
        ]);
    }

    /**
     * Delete a category
     *
     * @group Blog Management - Admin
     * @authenticated
     * @description Delete category (creator or admin only)
     *
     * @urlParam category required The category ID
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Category deleted successfully"
     * }
     */
    public function destroy(Category $category): JsonResponse
    {
        $this->logAuditAction('delete', Category::class, $category->id, $category->only(['name', 'slug']), null, "Deleted category: {$category->name}");
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
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
