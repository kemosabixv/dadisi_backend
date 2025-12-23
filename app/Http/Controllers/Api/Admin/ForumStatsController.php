<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumThread;
use App\Models\ForumPost;
use App\Models\ForumCategory;
use App\Models\ForumTag;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ForumStatsController extends Controller
{
    /**
     * Get forum overview statistics.
     * 
     * @group Admin Forum
     * @authenticated
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ForumThread::class);

        $stats = [
            'total_threads' => ForumThread::count(),
            'total_posts' => ForumPost::count(),
            'total_categories' => ForumCategory::count(),
            'total_tags' => ForumTag::count(),
            'total_groups' => Group::count(),
            'total_members' => User::count(), // Total platform users
            'threads_trend' => $this->getTrend(ForumThread::class),
            'posts_trend' => $this->getTrend(ForumPost::class),
            'recent_activity' => $this->getRecentActivity(),
        ];

        return response()->json([
            'data' => $stats
        ]);
    }

    /**
     * Get a simple trend (count from last 30 days vs previous 30 days).
     */
    private function getTrend($modelClass): int
    {
        $last30 = $modelClass::where('created_at', '>=', now()->subDays(30))->count();
        $prev30 = $modelClass::where('created_at', '>=', now()->subDays(60))
            ->where('created_at', '<', now()->subDays(30))
            ->count();

        if ($prev30 === 0) return $last30 > 0 ? 100 : 0;
        
        return (int) (($last30 - $prev30) / $prev30 * 100);
    }

    /**
     * Get recent forum activity.
     */
    private function getRecentActivity(): array
    {
        return ForumThread::with(['user:id,username', 'category:id,name'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($thread) {
                return [
                    'id' => $thread->id,
                    'type' => 'thread',
                    'title' => $thread->title,
                    'user' => $thread->user->username ?? 'Unknown',
                    'category' => $thread->category->name ?? 'None',
                    'created_at' => $thread->created_at,
                ];
            })
            ->toArray();
    }
}
