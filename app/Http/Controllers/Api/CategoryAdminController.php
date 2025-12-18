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
     * Get Category Creation Metadata
     *
     * Retrieves any necessary metadata or configuration required before creating a new category.
     * (Currently returns a readiness message, but extensible for future validation rules).
     *
     * @group Blog Admin - Categories
     * @groupDescription Administrative endpoints for managing blog categories (taxonomy). Categories organize posts into broader topics.
     * @authenticated
     *
     * @response 200 {
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
     * List Categories (Admin)
     *
     * Retrieves a paginated list of all blog categories.
     * Supports searching by name or description.
     *
     * @group Blog Admin - Categories
     * @authenticated
     *
     * @queryParam search string optional Keyword search for category name or description. Example: news
     * @queryParam per_page integer optional Number of items per page. Example: 20
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
        $response = array_merge($categories->toArray(), [
            'success' => true,
        ]);

        return response()->json($response);
    }

    /**
     * Create New Category
     *
     * Registers a new blog category in the system.
     * The slug is automatically generated from the name if not provided.
     *
     * @group Blog Admin - Categories
     * @authenticated
     *
     * @bodyParam name string required Unique name for the category. Example: Technology
     * @bodyParam slug string optional Unique URL-friendly slug. Auto-generated if empty. Example: technology
     * @bodyParam description string optional Short description of the category topic. Example: Technology news and updates
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
     * Get Category Details
     *
     * Retrieves detailed information about a specific category.
     * (Note: Post counts are computed fields available in this view).
     *
     * @group Blog Admin - Categories
     * @authenticated
     *
     * @urlParam category integer required The category ID. Example: 1
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
     * Get Category Edit Metadata
     *
     * Retrieves the category data formatted for the "Edit Category" form.
     *
     * @group Blog Admin - Categories
     * @authenticated
     *
     * @urlParam category integer required The category ID. Example: 1
     *
     * @response 200 {
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
     * Update Category
     *
     * Modifies the details of an existing category.
     * All fields are optional to allow for partial updates.
     *
     * @group Blog Admin - Categories
     * @authenticated
     *
     * @urlParam category integer required The category ID. Example: 1
     * @bodyParam name string optional Update name (must be unique). Example: Updated Category
     * @bodyParam slug string optional Update slug (must be unique). Example: updated-category
     * @bodyParam description string optional Update description. Example: Updated description
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
     * Delete Category
     *
     * Permanently removes a category from the system.
     *
     * @group Blog Admin - Categories
     * @authenticated
     *
     * @urlParam category integer required The category ID. Example: 1
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
