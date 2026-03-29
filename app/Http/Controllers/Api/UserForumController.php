<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ForumThread;
use App\Models\ForumPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group User Forum Activity
 *
 * @groupDescription Authenticated endpoints for a user to view their own forum threads and replies.
 */
class UserForumController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * List my threads
     *
     * Returns a paginated list of forum threads created by the authenticated user.
     *
     * @authenticated
     *
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 50). Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "data": [
     *       {
     *         "id": 1,
     *         "title": "My first thread",
     *         "slug": "my-first-thread-abc123",
     *         "posts_count": 3,
     *         "views_count": 42,
     *         "is_pinned": false,
     *         "is_locked": false,
     *         "created_at": "2025-01-01T10:00:00Z",
     *         "category": { "id": 1, "name": "General", "slug": "general" }
     *       }
     *     ],
     *     "total": 5,
     *     "per_page": 15,
     *     "current_page": 1,
     *     "last_page": 1
     *   }
     * }
     */
    public function myThreads(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $perPage = min((int) $request->input('per_page', 15), 50);

            $threads = ForumThread::where('user_id', $user->id)
                ->with(['category:id,name,slug'])
                ->withCount('posts')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Calendar-month count — matches backend quota enforcement logic
            $thisMonthCount = ForumThread::where('user_id', $user->id)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $threads->items(),
                    'total' => $threads->total(),
                    'per_page' => $threads->perPage(),
                    'current_page' => $threads->currentPage(),
                    'last_page' => $threads->lastPage(),
                    'this_month_count' => $thisMonthCount,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user forum threads', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve your threads.',
            ], 500);
        }
    }

    /**
     * List my replies
     *
     * Returns a paginated list of forum posts (replies) made by the authenticated user,
     * including the parent thread title and slug.
     *
     * @authenticated
     *
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 50). Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "data": [
     *       {
     *         "id": 10,
     *         "content": "Great discussion!",
     *         "created_at": "2025-01-02T12:00:00Z",
     *         "thread": { "id": 1, "title": "My first thread", "slug": "my-first-thread-abc123" }
     *       }
     *     ],
     *     "total": 12,
     *     "per_page": 15,
     *     "current_page": 1,
     *     "last_page": 1
     *   }
     * }
     */
    public function myReplies(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $perPage = min((int) $request->input('per_page', 15), 50);

            $posts = ForumPost::where('user_id', $user->id)
                ->with(['thread:id,title,slug'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Calendar-month count — matches backend quota enforcement logic
            $thisMonthCount = ForumPost::where('user_id', $user->id)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $posts->items(),
                    'total' => $posts->total(),
                    'per_page' => $posts->perPage(),
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'this_month_count' => $thisMonthCount,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user forum replies', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve your replies.',
            ], 500);
        }
    }
}
