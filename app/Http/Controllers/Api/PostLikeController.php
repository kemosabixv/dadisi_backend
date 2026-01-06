<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Like;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostLikeController extends Controller
{
    /**
     * Toggle a like or dislike on a post.
     *
     * @urlParam slug string required The post slug. Example: my-first-blog-post
     * @bodyParam type string required The type of vote: 'like' or 'dislike'. Example: like
     */
    public function toggle(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:like,dislike',
        ]);

        $post = Post::where('slug', $slug)->firstOrFail();
        $user = $request->user();
        $type = $request->type;

        // Check for existing vote
        $existingLike = $post->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            if ($existingLike->type === $type) {
                // Same type: toggle off (remove)
                $existingLike->delete();

                return response()->json([
                    'success' => true,
                    'action' => 'removed',
                    'message' => ucfirst($type) . ' removed.',
                    'likes_count' => $post->fresh()->likes_count,
                    'dislikes_count' => $post->fresh()->dislikes_count,
                ]);
            } else {
                // Different type: switch vote
                $existingLike->update(['type' => $type]);

                return response()->json([
                    'success' => true,
                    'action' => 'switched',
                    'message' => 'Vote changed to ' . $type . '.',
                    'likes_count' => $post->fresh()->likes_count,
                    'dislikes_count' => $post->fresh()->dislikes_count,
                ]);
            }
        }

        // No existing vote: create new
        $like = $post->likes()->create([
            'user_id' => $user->id,
            'type' => $type,
        ]);

        // Notify the post owner
        if ($post->user_id !== $user->id) {
            $post->author->notify(new \App\Notifications\NewLikeNotification($like, $post));
        }

        return response()->json([
            'success' => true,
            'action' => 'added',
            'message' => ucfirst($type) . ' added.',
            'likes_count' => $post->fresh()->likes_count,
            'dislikes_count' => $post->fresh()->dislikes_count,
        ], 201);
    }

    /**
     * Get the current user's vote status for a post.
     *
     * @urlParam slug string required The post slug. Example: my-first-blog-post
     */
    public function status(Request $request, string $slug): JsonResponse
    {
        $post = Post::where('slug', $slug)->firstOrFail();
        $user = $request->user();

        $userLike = $post->likes()->where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'user_vote' => $userLike?->type,
                'likes_count' => $post->likes_count,
                'dislikes_count' => $post->dislikes_count,
            ],
        ]);
    }

    /**
     * Get the list of users who liked or disliked a post.
     * 
     * @urlParam slug string required The post slug.
     */
    public function likers(Request $request, string $slug): JsonResponse
    {
        $post = Post::where('slug', $slug)->firstOrFail();
        
        $likers = $post->likes()
            ->with('user:id,username,profile_picture_path')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $likers,
        ]);
    }
}
