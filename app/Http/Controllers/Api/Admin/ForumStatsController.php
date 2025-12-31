<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumThread;
use App\Services\Contracts\ForumServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @group Admin Forum
 * @groupDescription Endpoints for forum statistics and overview.
 */
class ForumStatsController extends Controller
{
    public function __construct(
        private ForumServiceContract $forumService
    ) {
        $this->middleware(['auth:sanctum', 'admin']);
    }

    /**
     * Get Forum Overview Stats
     * 
     * Returns aggregate statistics for threads, posts, categories, and recent activity.
     * 
     * @group Admin Forum
     * @authenticated
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "total_threads": 150,
     *     "total_posts": 1200,
     *     "total_categories": 12,
     *     "threads_trend": 15,
     *     "recent_activity": [...]
     *   }
     * }
     */
    public function index(): JsonResponse
    {
        try {
            $this->authorize('viewAny', ForumThread::class);

            $stats = $this->forumService->getStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve forum statistics', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve statistics'], 500);
        }
    }
}
