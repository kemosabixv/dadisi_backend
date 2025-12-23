<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ForumPost;
use App\Models\ForumThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForumPostController extends Controller
{

    /**
     * Create a new post.
     *
     * Adds a reply to a specific forum thread.
     * Returns the created post details with user metadata.
     *
     * @group Forum Posts
     * @authenticated
     * @urlParam thread string required The thread slug or ID. Example: welcome-to-dadisi-forum-x1y2z3
     * @bodyParam content string required The content of the reply. Example: This is a great initiative!
     *
     * @response 201 {
     *   "data": {
     *     "id": 21,
     *     "content": "This is a great initiative!",
     *     "user": {"id": 2, "username": "jane_doe"}
     *   },
     *   "message": "Reply posted successfully."
     * }
     */
    public function store(Request $request, ForumThread $thread): JsonResponse
    {
        $this->authorize('create', [ForumPost::class, $thread]);

        if ($thread->is_locked) {
            return response()->json([
                'message' => 'This thread is locked. You cannot reply.',
            ], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|min:3',
        ]);

        $post = $thread->posts()->create([
            'user_id' => Auth::id(),
            'content' => $validated['content'],
        ]);

        return response()->json([
            'data' => $post->load('user:id,username,profile_picture_path'),
            'message' => 'Reply posted successfully.',
        ], 201);
    }


    /**
     * Update a post.
     * 
     * @group Forum Posts
     * @authenticated
     * 
     * @urlParam post integer required The ID of the post.
     * @bodyParam content string required The content of the post.
     */
    public function update(Request $request, ForumPost $post): JsonResponse
    {
        $this->authorize('update', $post);

        $validated = $request->validate([
            'content' => 'required|string|min:3',
        ]);

        $post->update([
            'content' => $validated['content'],
            'is_edited' => true,
        ]);

        return response()->json([
            'data' => $post,
            'message' => 'Post updated successfully.',
        ]);
    }


    /**
     * Delete a post.
     * 
     * @group Forum Posts
     * @authenticated
     * @urlParam post integer required The ID of the post.
     */
    public function destroy(ForumPost $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return response()->json([
            'message' => 'Post deleted successfully.',
        ]);
    }
}
