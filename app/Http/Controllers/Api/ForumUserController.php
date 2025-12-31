<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\ForumUserServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ForumUserController extends Controller
{
    public function __construct(private ForumUserServiceContract $forumUserService)
    {
    }
    /**
     * List active forum members.
     * 
     * @group Forum Users
     * @unauthenticated
     * 
     * @queryParam search string Search by username. Example: jane
     * @queryParam sort string Sort by: post_count, joined_date, recent_activity. Default: post_count. Example: recent_activity
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Results per page (default 20). Example: 20
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 2,
     *       "username": "jane_doe",
     *       "profile_picture_url": "https://api.dadisilab.com/storage/profiles/jane.jpg",
     *       "joined_at": "2025-11-20T10:00:00Z",
     *       "thread_count": 3,
     *       "post_count": 12,
     *       "total_contributions": 15
     *     }
     *   ],
     *   "links": {"first": "...", "last": "..."},
     *   "meta": {"current_page": 1, "last_page": 1, "total": 1}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->input('search'),
                'sort' => $request->input('sort', 'post_count'),
                'per_page' => $request->input('per_page', 20),
            ];

            $users = $this->forumUserService->listForumMembers($filters);

            return response()->json(['success' => true, 'data' => $users]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve forum users', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve forum users'], 500);
        }
    }
}
