<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostCommentController extends Controller
{
    /**
     * List comments for a post.
     *
     * @urlParam slug string required The post slug. Example: my-first-blog-post
     */
    public function index(string $slug): JsonResponse
    {
        $post = Post::where('slug', $slug)->firstOrFail();

        $comments = $post->comments()
            ->with(['user:id,username,profile_picture_path', 'replies.user:id,username,profile_picture_path'])
            ->topLevel()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $comments,
        ]);
    }

    /**
     * Store a new comment on a post.
     *
     * @urlParam slug string required The post slug. Example: my-first-blog-post
     * @bodyParam body string required The comment text. Example: Great article!
     * @bodyParam parent_id integer optional ID of parent comment for replies. Example: 5
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'body' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        $post = Post::where('slug', $slug)->firstOrFail();

        if (!$post->allow_comments) {
            return response()->json([
                'success' => false,
                'message' => 'Comments are disabled for this post.',
            ], 403);
        }

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->body,
            'parent_id' => $request->parent_id,
        ]);

        // Notify the post owner if they are not the commenter
        if ($post->user_id !== $comment->user_id) {
            $post->author->notify(new \App\Notifications\NewCommentNotification($comment, $post));
        }

        $comment->load('user:id,username,profile_picture_path');

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully.',
            'data' => $comment,
        ], 201);
    }

    /**
     * Delete a comment.
     *
     * @urlParam comment integer required The comment ID. Example: 1
     */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();

        // Only the owner or admin can delete
        if ($comment->user_id !== $user->id && !$user->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this comment.',
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully.',
        ]);
    }
}
