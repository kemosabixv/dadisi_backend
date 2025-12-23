<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ForumUserController extends Controller
{
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
        $query = User::query()
            ->select([
                'users.id',
                'users.username',
                'users.profile_picture_path',
                'users.created_at as joined_at',
            ])
            ->withCount(['forumThreads', 'forumPosts'])
            ->leftJoin('member_profiles', 'users.id', '=', 'member_profiles.user_id')
            ->where(function ($q) {
                // Only show users with public profiles enabled or no profile (default public)
                $q->whereNull('member_profiles.id')
                  ->orWhere('member_profiles.public_profile_enabled', true);
            })
            ->whereNotNull('users.email_verified_at'); // Only verified users

        // Search by username
        if ($request->has('search') && !empty($request->search)) {
            $query->where('users.username', 'like', '%' . $request->search . '%');
        }

        // Sorting
        $sort = $request->get('sort', 'post_count');
        switch ($sort) {
            case 'joined_date':
                $query->orderByDesc('users.created_at');
                break;
            case 'recent_activity':
                $query->orderByDesc(
                    DB::raw('GREATEST(COALESCE((SELECT MAX(created_at) FROM forum_threads WHERE user_id = users.id), "1970-01-01"), COALESCE((SELECT MAX(created_at) FROM forum_posts WHERE user_id = users.id), "1970-01-01"))')
                );
                break;
            case 'post_count':
            default:
                $query->orderByDesc(DB::raw('forum_threads_count + forum_posts_count'));
                break;
        }

        $perPage = min($request->get('per_page', 20), 50);
        $users = $query->paginate($perPage);

        // Transform data for response
        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'profile_picture_url' => $user->profile_picture_path 
                    ? url('storage/' . $user->profile_picture_path) 
                    : null,
                'joined_at' => $user->joined_at,
                'thread_count' => $user->forum_threads_count,
                'post_count' => $user->forum_posts_count,
                'total_contributions' => $user->forum_threads_count + $user->forum_posts_count,
            ];
        });

        return response()->json($users);
    }
}
