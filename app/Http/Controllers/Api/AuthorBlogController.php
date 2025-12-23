<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @group Author Blog Management
 *
 * APIs for authenticated authors to manage their own blog categories and tags.
 * Authors can create, update, and request deletion (soft-delete requires staff approval).
 */
class AuthorBlogController extends Controller
{
    /**
     * List author's categories
     *
     * Returns all categories created by the authenticated user.
     *
     * @authenticated
     * @response 200 {"data": [{"id": 1, "name": "Agriculture", "slug": "agriculture", "description": "Urban farming tips", "post_count": 5, "requested_deletion_at": null}]}
     */
    public function listCategories(Request $request): JsonResponse
    {
        // Return all categories so authors can select from system categories
        $categories = Category::orderBy('name')->get();

        return response()->json($categories);
    }

    /**
     * Create a category
     *
     * Creates a new category owned by the authenticated user.
     *
     * @authenticated
     * @bodyParam name string required The category name. Example: Technology
     * @bodyParam description string optional The category description. Example: Posts about technology
     * @response 201 {"id": 1, "name": "Technology", "slug": "technology", "description": null, "post_count": 0}
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name',
            'description' => 'nullable|string|max:255',
        ]);

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($category, 201);
    }

    /**
     * Update a category
     *
     * Updates a category owned by the authenticated user.
     *
     * @authenticated
     * @urlParam id integer required The category ID. Example: 1
     * @bodyParam name string required The category name. Example: Updated Technology
     * @bodyParam description string optional The category description.
     * @response 200 {"id": 1, "name": "Updated Technology", "slug": "updated-technology", "description": null, "post_count": 5}
     */
    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        // Verify ownership
        if ($category->created_by !== $request->user()->id) {
            return response()->json(['message' => 'You can only update your own categories.'], 403);
        }

        // Cannot update if pending deletion
        if ($category->requested_deletion_at) {
            return response()->json(['message' => 'Cannot update a category pending deletion.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name,' . $category->id,
            'description' => 'nullable|string|max:255',
        ]);

        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json($category->fresh());
    }

    /**
     * Request category deletion
     *
     * Submits a deletion request for a category owned by the authenticated user.
     * Actual deletion requires staff approval.
     *
     * @authenticated
     * @urlParam id integer required The category ID. Example: 1
     * @response 200 {"message": "Deletion request submitted for staff review."}
     */
    public function requestCategoryDeletion(Request $request, Category $category): JsonResponse
    {
        // Verify ownership
        if ($category->created_by !== $request->user()->id) {
            return response()->json(['message' => 'You can only delete your own categories.'], 403);
        }

        // Already pending
        if ($category->requested_deletion_at) {
            return response()->json(['message' => 'Deletion already requested.'], 400);
        }

        $category->requestDeletion($request->user()->id);

        return response()->json(['message' => 'Deletion request submitted for staff review.']);
    }

    /**
     * List author's tags
     *
     * Returns all tags created by the authenticated user.
     *
     * @authenticated
     * @response 200 {"data": [{"id": 1, "name": "Laravel", "slug": "laravel", "post_count": 3, "requested_deletion_at": null}]}
     */
    public function listTags(Request $request): JsonResponse
    {
        // Return all tags so authors can select from system tags
        $tags = Tag::orderBy('name')->get();

        return response()->json($tags);
    }

    /**
     * Create a tag
     *
     * Creates a new tag owned by the authenticated user.
     *
     * @authenticated
     * @bodyParam name string required The tag name. Example: Laravel
     * @response 201 {"id": 1, "name": "Laravel", "slug": "laravel", "post_count": 0}
     */
    public function storeTag(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:tags,name',
        ]);

        $tag = Tag::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($tag, 201);
    }

    /**
     * Update a tag
     *
     * Updates a tag owned by the authenticated user.
     *
     * @authenticated
     * @urlParam id integer required The tag ID. Example: 1
     * @bodyParam name string required The tag name. Example: Updated Laravel
     * @response 200 {"id": 1, "name": "Updated Laravel", "slug": "updated-laravel", "post_count": 3}
     */
    public function updateTag(Request $request, Tag $tag): JsonResponse
    {
        // Verify ownership
        if ($tag->created_by !== $request->user()->id) {
            return response()->json(['message' => 'You can only update your own tags.'], 403);
        }

        // Cannot update if pending deletion
        if ($tag->requested_deletion_at) {
            return response()->json(['message' => 'Cannot update a tag pending deletion.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:tags,name,' . $tag->id,
        ]);

        $tag->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
        ]);

        return response()->json($tag->fresh());
    }

    /**
     * Request tag deletion
     *
     * Submits a deletion request for a tag owned by the authenticated user.
     * Actual deletion requires staff approval.
     *
     * @authenticated
     * @urlParam id integer required The tag ID. Example: 1
     * @response 200 {"message": "Deletion request submitted for staff review."}
     */
    public function requestTagDeletion(Request $request, Tag $tag): JsonResponse
    {
        // Verify ownership
        if ($tag->created_by !== $request->user()->id) {
            return response()->json(['message' => 'You can only delete your own tags.'], 403);
        }

        // Already pending
        if ($tag->requested_deletion_at) {
            return response()->json(['message' => 'Deletion already requested.'], 400);
        }

        $tag->requestDeletion($request->user()->id);

        return response()->json(['message' => 'Deletion request submitted for staff review.']);
    }
}
