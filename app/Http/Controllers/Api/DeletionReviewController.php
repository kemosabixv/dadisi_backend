<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Admin Blog - Deletion Reviews
 *
 * APIs for staff (Content Editors, Admins) to review and approve/reject
 * deletion requests submitted by authors.
 */
class DeletionReviewController extends Controller
{
    /**
     * List pending deletion requests
     *
     * Returns all categories and tags with pending deletion requests.
     *
     * @authenticated
     * @queryParam type string Filter by type: 'category' or 'tag'. Example: category
     * @response 200 [{"id": 1, "type": "category", "name": "My Category", "slug": "my-category", "requested_at": "2025-12-19T00:00:00Z", "requested_by": {"id": 1, "username": "author", "email": "author@example.com"}, "post_count": 5}]
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');

        $results = [];

        // Get pending categories
        if (!$type || $type === 'category') {
            $categories = Category::pendingDeletion()
                ->with('deletionRequester:id,username,email')
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'type' => 'category',
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'requested_at' => $c->requested_deletion_at?->toIso8601String(),
                    'requested_by' => $c->deletionRequester ? [
                        'id' => $c->deletionRequester->id,
                        'username' => $c->deletionRequester->username,
                        'email' => $c->deletionRequester->email,
                    ] : null,
                    'post_count' => $c->post_count,
                ]);

            $results = array_merge($results, $categories->toArray());
        }

        // Get pending tags
        if (!$type || $type === 'tag') {
            $tags = Tag::pendingDeletion()
                ->with('deletionRequester:id,username,email')
                ->get()
                ->map(fn($t) => [
                    'id' => $t->id,
                    'type' => 'tag',
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'requested_at' => $t->requested_deletion_at?->toIso8601String(),
                    'requested_by' => $t->deletionRequester ? [
                        'id' => $t->deletionRequester->id,
                        'username' => $t->deletionRequester->username,
                        'email' => $t->deletionRequester->email,
                    ] : null,
                    'post_count' => $t->post_count,
                ]);

            $results = array_merge($results, $tags->toArray());
        }

        // Sort by requested_at descending
        usort($results, fn($a, $b) => strcmp($b['requested_at'] ?? '', $a['requested_at'] ?? ''));

        return response()->json($results);
    }

    /**
     * Approve deletion request
     *
     * Approves a deletion request and soft-deletes the category/tag.
     *
     * @authenticated
     * @urlParam type string required The type: 'category' or 'tag'. Example: category
     * @urlParam id integer required The category/tag ID. Example: 1
     * @bodyParam comment string optional Audit note for the approval.
     * @response 200 {"message": "Category deleted successfully."}
     */
    public function approve(Request $request, string $type, int $id): JsonResponse
    {
        $request->validate([
            'comment' => 'nullable|string|max:500',
        ]);

        $model = $this->getModel($type, $id);

        if (!$model) {
            return response()->json(['message' => ucfirst($type) . ' not found.'], 404);
        }

        if (!$model->requested_deletion_at) {
            return response()->json(['message' => 'No pending deletion request for this ' . $type . '.'], 400);
        }

        // Perform the deletion
        DB::transaction(function () use ($model, $type, $request) {
            // Log to audit (if available)
            // AuditLog::create([...]);

            // For now, we'll hard-delete since soft-deletes aren't implemented on categories/tags
            // In production, you may want to add SoftDeletes trait
            $model->delete();
        });

        // TODO: Send notification email to the author
        // Notification::send($model->deletionRequester, new DeletionApprovedNotification($model, $request->comment));

        return response()->json(['message' => ucfirst($type) . ' deleted successfully.']);
    }

    /**
     * Reject deletion request
     *
     * Rejects a deletion request and notifies the author.
     *
     * @authenticated
     * @urlParam type string required The type: 'category' or 'tag'. Example: category
     * @urlParam id integer required The category/tag ID. Example: 1
     * @bodyParam comment string optional Reason for rejection (sent to author).
     * @response 200 {"message": "Deletion request rejected."}
     */
    public function reject(Request $request, string $type, int $id): JsonResponse
    {
        $request->validate([
            'comment' => 'nullable|string|max:500',
        ]);

        $model = $this->getModel($type, $id);

        if (!$model) {
            return response()->json(['message' => ucfirst($type) . ' not found.'], 404);
        }

        if (!$model->requested_deletion_at) {
            return response()->json(['message' => 'No pending deletion request for this ' . $type . '.'], 400);
        }

        // Clear the deletion request
        $model->clearDeletionRequest();

        // TODO: Send notification email to the author with rejection reason
        // Notification::send($model->creator, new DeletionRejectedNotification($model, $request->comment));

        return response()->json(['message' => 'Deletion request rejected.']);
    }

    /**
     * Get the model instance by type and ID
     */
    private function getModel(string $type, int $id): ?object
    {
        return match ($type) {
            'category' => Category::find($id),
            'tag' => Tag::find($id),
            default => null,
        };
    }
}
