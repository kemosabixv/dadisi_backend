<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ForumThread;
use App\Models\ForumCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class ForumThreadController extends Controller
{

    /**
     * List all threads.
     * 
     * @group Forum Threads
     * @unauthenticated
     * 
     * @queryParam category string filter by category slug
     * @queryParam page integer page number
     * 
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 10,
     *       "title": "Welcome to Dadisi Forum!",
     *       "slug": "welcome-to-dadisi-forum-x1y2z3",
     *       "user": {"id": 1, "username": "admin"},
     *       "category": {"id": 1, "name": "Announcements", "slug": "announcements"},
     *       "posts_count": 1,
     *       "views_count": 150,
     *       "is_pinned": true,
     *       "created_at": "2025-12-01T10:00:00Z"
     *     }
     *   ],
     *   "links": {"first": "...", "last": "...", "prev": null, "next": null},
     *   "meta": {"current_page": 1, "from": 1, "last_page": 1, "per_page": 20, "total": 1}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $query = ForumThread::with(['user:id,username,profile_picture_path', 'category:id,name,slug', 'lastPost.user:id,username'])
            ->pinnedFirst();

        if ($request->has('category')) {
            $category = ForumCategory::where('slug', $request->category)->firstOrFail();
            $query->where('category_id', $category->id);
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $threads = $query->paginate($request->get('per_page', 20));

        return response()->json($threads);
    }


    /**
     * Show a single thread.
     * 
     * @group Forum Threads
     * @unauthenticated
     * @urlParam thread string required The slug of the thread.
     * 
     * @response 200 {
     *   "thread": {
     *     "id": 10,
     *     "title": "Welcome to Dadisi Forum!",
     *     "user": {"id": 1, "username": "admin"},
     *     "category": {"id": 1, "name": "Announcements"}
     *   },
     *   "posts": {
     *     "data": [
     *       {
     *         "id": 20,
     *         "content": "Welcome everyone! This is our new community space.",
     *         "user": {"id": 1, "username": "admin"}
     *       }
     *     ],
     *     "total": 1
     *   }
     * }
     */
    public function show(ForumThread $thread): JsonResponse
    {
        $thread->incrementViews();

        $thread->load(['user:id,username,profile_picture_path', 'category:id,name,slug']);

        $posts = $thread->posts()
            ->with('user:id,username,profile_picture_path')
            ->oldest()
            ->paginate(20);

        return response()->json([
            'thread' => $thread,
            'posts' => $posts,
        ]);
    }


    /**
     * Create a new thread.
     * 
     * @group Forum Threads
     * @authenticated
     * 
     * @bodyParam category_id integer required The ID of the category.
     * @bodyParam county_id integer optional The ID of the county (if specific).
     * @bodyParam title string required The title of the thread.
     * @bodyParam content string required The content of the main post.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ForumThread::class);
 
        $validated = $request->validate([
            'category_id' => 'required|exists:forum_categories,id',
            'county_id' => 'nullable|exists:counties,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:10',
        ]);
 
        $thread = ForumThread::create([
            'category_id' => $validated['category_id'],
            'county_id' => $validated['county_id'] ?? null,
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']) . '-' . Str::random(6),
        ]);
 
        // Create the first post (the thread body)
        $thread->posts()->create([
            'user_id' => Auth::id(),
            'content' => $validated['content'],
        ]);
 
        return response()->json([
            'data' => $thread->load(['user:id,username', 'county:id,name']),
            'message' => 'Thread created successfully.',
        ], 201);
    }
 

    /**
     * Update a thread.
     * 
     * @group Forum Threads
     * @authenticated
     * 
     * @urlParam thread string required The slug of the thread.
     * @bodyParam title string required The title of the thread.
     * @bodyParam is_pinned boolean optional Pin the thread (Moderator only).
     * @bodyParam is_locked boolean optional Lock the thread (Moderator only).
     * @bodyParam county_id integer optional The ID of the county.
     */
    public function update(Request $request, ForumThread $thread): JsonResponse
    {
        $this->authorize('update', $thread);
 
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'is_pinned' => 'sometimes|boolean',
            'is_locked' => 'sometimes|boolean',
            'county_id' => 'sometimes|nullable|exists:counties,id',
        ]);
 
        // Only moderators can pin/lock
        if (isset($validated['is_pinned']) || isset($validated['is_locked'])) {
            if (!Auth::user()->hasAnyRole(['admin', 'super_admin', 'moderator'])) {
                unset($validated['is_pinned'], $validated['is_locked']);
            }
        }
 
        $thread->update($validated);
 
        return response()->json([
            'data' => $thread->load(['user:id,username', 'county:id,name']),
            'message' => 'Thread updated successfully.',
        ]);
    }
 

    /**
     * Delete a thread.
     * 
     * @group Forum Threads
     * @authenticated
     * @urlParam thread string required The slug of the thread.
     */
    public function destroy(ForumThread $thread): JsonResponse
    {
        $this->authorize('delete', $thread);
 
        $thread->delete();
 
        return response()->json([
            'message' => 'Thread deleted successfully.',
        ]);
    }


    /**
     * Pin a thread.
     * 
     * @group Forum Threads
     * @authenticated
     * @urlParam thread string required The slug of the thread.
     */
    public function pin(ForumThread $thread): JsonResponse
    {
        $this->authorize('moderate', $thread);
        $thread->update(['is_pinned' => true]);
        return response()->json(['data' => $thread, 'message' => 'Thread pinned.']);
    }


    /**
     * Unpin a thread.
     * 
     * @group Forum Threads
     * @authenticated
     * @urlParam thread string required The slug of the thread.
     */
    public function unpin(ForumThread $thread): JsonResponse
    {
        $this->authorize('moderate', $thread);
        $thread->update(['is_pinned' => false]);
        return response()->json(['data' => $thread, 'message' => 'Thread unpinned.']);
    }


    /**
     * Lock a thread.
     * 
     * @group Forum Threads
     * @authenticated
     * @urlParam thread string required The slug of the thread.
     */
    public function lock(ForumThread $thread): JsonResponse
    {
        $this->authorize('moderate', $thread);
        $thread->update(['is_locked' => true]);
        return response()->json(['data' => $thread, 'message' => 'Thread locked.']);
    }


    /**
     * Unlock a thread.
     * 
     * @group Forum Threads
     * @authenticated
     * @urlParam thread string required The slug of the thread.
     */
    public function unlock(ForumThread $thread): JsonResponse
    {
        $this->authorize('moderate', $thread);
        $thread->update(['is_locked' => false]);
        return response()->json(['data' => $thread, 'message' => 'Thread unlocked.']);
    }
}
