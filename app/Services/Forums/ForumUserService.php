<?php

namespace App\Services\Forums;

use App\Services\Contracts\ForumUserServiceContract;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Forum User Service
 *
 * Handles forum user operations including listing members and member profiles.
 */
class ForumUserService implements ForumUserServiceContract
{
    /**
     * List active forum members with search, sort, and pagination
     */
    public function listForumMembers(array $filters = []): LengthAwarePaginator
    {
        try {
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
            if (!empty($filters['search'])) {
                $query->where('users.username', 'like', '%' . $filters['search'] . '%');
            }

            // Sorting
            $sort = $filters['sort'] ?? 'post_count';
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

            $perPage = min($filters['per_page'] ?? 20, 50);
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

            return $users;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve forum users', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
