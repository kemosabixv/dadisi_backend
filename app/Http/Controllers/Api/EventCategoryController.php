<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventCategoryResource;
use App\Models\EventCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EventCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'admin'])->except(['index', 'show']);
    }

    /**
     * List Categories
     * 
     * @group Event Categories
     */
    public function index()
    {
        $categories = EventCategory::with('children')->whereNull('parent_id')->active()->orderBy('sort_order')->get();
        return EventCategoryResource::collection($categories);
    }

    /**
     * Get Category Details
     * 
     * @group Event Categories
     */
    public function show(EventCategory $category)
    {
        return new EventCategoryResource($category->load('children'));
    }

    /**
     * Create Category (Admin Only)
     * 
     * @group Event Categories
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:event_categories,slug',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'parent_id' => 'nullable|exists:event_categories,id',
            'image_path' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $category = EventCategory::create($validated);

        return new EventCategoryResource($category);
    }

    /**
     * Update Category (Admin Only)
     * 
     * @group Event Categories
     */
    public function update(Request $request, EventCategory $category)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255|unique:event_categories,slug,' . $category->id,
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'parent_id' => 'nullable|exists:event_categories,id',
            'image_path' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $category->update($validated);

        return new EventCategoryResource($category);
    }

    /**
     * Delete Category (Admin Only)
     * 
     * @group Event Categories
     */
    public function destroy(EventCategory $category)
    {
        $category->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
