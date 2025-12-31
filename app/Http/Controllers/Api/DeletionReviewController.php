<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\BlogTaxonomyServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Admin - Deletion Reviews
 */
class DeletionReviewController extends Controller
{
    protected BlogTaxonomyServiceContract $taxonomyService;

    public function __construct(BlogTaxonomyServiceContract $taxonomyService)
    {
        $this->taxonomyService = $taxonomyService;
        $this->middleware(['auth:sanctum', 'admin']);
    }

    /**
     * List Pending Requests
     * 
     * @group Admin - Deletion Reviews
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $type = $request->query('type');
            $results = $this->taxonomyService->listPendingDeletions($type);

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            Log::error('DeletionReviewController index failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve deletion requests'], 500);
        }
    }

    /**
     * Approve Request
     * 
     * @group Admin - Deletion Reviews
     * @authenticated
     */
    public function approve(Request $request, string $type, int $id): JsonResponse
    {
        try {
            $request->validate([
                'comment' => 'nullable|string|max:500',
            ]);

            $success = $this->taxonomyService->approveDeletion($request->user(), $type, $id, $request->comment);

            if ($success === false) {
                // Check if it's a 404 (not found) or 400 (not pending)
                $model = match ($type) {
                    'category' => \App\Models\Category::find($id),
                    'tag' => \App\Models\Tag::find($id),
                    default => null,
                };
                
                if (!$model) {
                    return response()->json(['success' => false, 'message' => 'Request not found.'], 404);
                }
                
                return response()->json(['success' => false, 'message' => 'Item is not pending deletion.'], 400);
            }

            return response()->json(['success' => true, 'message' => ucfirst($type) . ' deleted successfully.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('DeletionReviewController approve failed', ['error' => $e->getMessage(), 'type' => $type, 'id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to approve deletion'], 500);
        }
    }

    /**
     * Reject Request
     * 
     * @group Admin - Deletion Reviews
     * @authenticated
     */
    public function reject(Request $request, string $type, int $id): JsonResponse
    {
        try {
            $request->validate([
                'comment' => 'nullable|string|max:500',
            ]);

            $success = $this->taxonomyService->rejectDeletion($request->user(), $type, $id, $request->comment);

            if ($success === false) {
                // Check if it's a 404 (not found) or 400 (not pending)
                $model = match ($type) {
                    'category' => \App\Models\Category::find($id),
                    'tag' => \App\Models\Tag::find($id),
                    default => null,
                };
                
                if (!$model) {
                    return response()->json(['success' => false, 'message' => 'Request not found.'], 404);
                }
                
                return response()->json(['success' => false, 'message' => 'Item is not pending deletion.'], 400);
            }

            return response()->json(['success' => true, 'message' => 'Deletion request rejected.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('DeletionReviewController reject failed', ['error' => $e->getMessage(), 'type' => $type, 'id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to reject deletion'], 500);
        }
    }
}
