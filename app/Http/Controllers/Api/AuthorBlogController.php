<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tag;
use App\Services\Contracts\BlogTaxonomyServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Blog - Author
 */
class AuthorBlogController extends Controller
{
    protected BlogTaxonomyServiceContract $taxonomyService;

    public function __construct(BlogTaxonomyServiceContract $taxonomyService)
    {
        $this->taxonomyService = $taxonomyService;
        $this->middleware('auth:sanctum');
    }

    /**
     * List Categories
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function listCategories(Request $request): JsonResponse
    {
        try {
            // Authors see all categories to select from
            $categories = Category::orderBy('name')->get();
            return response()->json(['success' => true, 'data' => $categories]);
        } catch (\Exception $e) {
            Log::error('AuthorBlogController listCategories failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to list categories'], 500);
        }
    }

    /**
     * Store Category
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function storeCategory(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:categories,name',
                'description' => 'nullable|string|max:255',
            ]);

            $category = $this->taxonomyService->createCategory($request->user(), $validated);

            return response()->json(['success' => true, 'data' => $category], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('AuthorBlogController storeCategory failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create category'], 500);
        }
    }

    /**
     * Update Category
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        try {
            if ($category->created_by !== $request->user()->id) {
                return response()->json(['success' => false, 'message' => 'You can only update your own categories.'], 403);
            }

            if ($category->requested_deletion_at) {
                return response()->json(['success' => false, 'message' => 'Cannot update a category pending deletion.'], 403);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:categories,name,' . $category->id,
                'description' => 'nullable|string|max:255',
            ]);

            $updatedCategory = $this->taxonomyService->updateCategory($request->user(), $category, $validated);

            return response()->json(['success' => true, 'data' => $updatedCategory]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('AuthorBlogController updateCategory failed', ['error' => $e->getMessage(), 'id' => $category->id]);
            return response()->json(['success' => false, 'message' => 'Failed to update category'], 500);
        }
    }

    /**
     * Request Category Deletion
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function requestCategoryDeletion(Request $request, Category $category): JsonResponse
    {
        try {
            if ($category->created_by !== $request->user()->id) {
                return response()->json(['success' => false, 'message' => 'You can only delete your own categories.'], 403);
            }

            if ($category->requested_deletion_at) {
                return response()->json(['success' => false, 'message' => 'Deletion already requested.'], 400);
            }

            $this->taxonomyService->requestCategoryDeletion($request->user(), $category);

            return response()->json(['success' => true, 'message' => 'Deletion request submitted for staff review.']);
        } catch (\Exception $e) {
            Log::error('AuthorBlogController requestCategoryDeletion failed', ['error' => $e->getMessage(), 'id' => $category->id]);
            return response()->json(['success' => false, 'message' => 'Failed to request deletion'], 500);
        }
    }

    /**
     * List Tags
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function listTags(Request $request): JsonResponse
    {
        try {
            $tags = Tag::orderBy('name')->get();
            return response()->json(['success' => true, 'data' => $tags]);
        } catch (\Exception $e) {
            Log::error('AuthorBlogController listTags failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to list tags'], 500);
        }
    }

    /**
     * Store Tag
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function storeTag(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:tags,name',
            ]);

            $tag = $this->taxonomyService->createTag($request->user(), $validated);

            return response()->json(['success' => true, 'data' => $tag], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('AuthorBlogController storeTag failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create tag'], 500);
        }
    }

    /**
     * Update Tag
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function updateTag(Request $request, Tag $tag): JsonResponse
    {
        try {
            if ($tag->created_by !== $request->user()->id) {
                return response()->json(['success' => false, 'message' => 'You can only update your own tags.'], 403);
            }

            if ($tag->requested_deletion_at) {
                return response()->json(['success' => false, 'message' => 'Cannot update a tag pending deletion.'], 403);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:tags,name,' . $tag->id,
            ]);

            $updatedTag = $this->taxonomyService->updateTag($request->user(), $tag, $validated);

            return response()->json(['success' => true, 'data' => $updatedTag]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('AuthorBlogController updateTag failed', ['error' => $e->getMessage(), 'id' => $tag->id]);
            return response()->json(['success' => false, 'message' => 'Failed to update tag'], 500);
        }
    }

    /**
     * Request Tag Deletion
     * 
     * @group Blog - Author
     * @authenticated
     */
    public function requestTagDeletion(Request $request, Tag $tag): JsonResponse
    {
        try {
            if ($tag->created_by !== $request->user()->id) {
                return response()->json(['success' => false, 'message' => 'You can only delete your own tags.'], 403);
            }

            if ($tag->requested_deletion_at) {
                return response()->json(['success' => false, 'message' => 'Deletion already requested.'], 400);
            }

            $this->taxonomyService->requestTagDeletion($request->user(), $tag);

            return response()->json(['success' => true, 'message' => 'Deletion request submitted for staff review.']);
        } catch (\Exception $e) {
            Log::error('AuthorBlogController requestTagDeletion failed', ['error' => $e->getMessage(), 'id' => $tag->id]);
            return response()->json(['success' => false, 'message' => 'Failed to request deletion'], 500);
        }
    }
}
