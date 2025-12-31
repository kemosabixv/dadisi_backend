<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ForumException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreForumThreadRequest;
use App\Http\Requests\Api\UpdateForumThreadRequest;
use App\Models\ForumThread;
use App\Services\Contracts\ForumServiceContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class ForumThreadController extends Controller
{
    public function __construct(
        private ForumServiceContract $threadService
    ) {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    /**
     * List all threads.
     * 
     * @group Forum Threads
     * @unauthenticated
     * 
     * @queryParam category string filter by category slug
     * @queryParam search string search by title
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
        try {
            $filters = [
                'category_slug' => $request->input('category'),
                'search' => $request->input('search'),
                'county_id' => $request->input('county_id'),
            ];

            $threads = $this->threadService->listThreads($filters, $request->get('per_page', 20));

            return response()->json(['success' => true, 'data' => $threads]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve forum threads', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve forum threads'], 500);
        }
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
    public function show(ForumThread $thread, Request $request): JsonResponse
    {
        try {
            $result = $this->threadService->getThreadWithPosts($thread, $request->get('per_page', 20));

            return response()->json([
                'success' => true, 
                'data' => $result,
            ]);
        } catch (ForumException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve forum thread', ['error' => $e->getMessage(), 'thread_id' => $thread->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve forum thread'], 500);
        }
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
     * @bodyParam tag_ids array optional Array of tag IDs.
     */
    public function store(StoreForumThreadRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', ForumThread::class);
     
            $validated = $request->validated();
            $thread = $this->threadService->createThread(auth()->user(), $validated);
     
            return response()->json([
                'success' => true,
                'data' => $thread,
                'message' => 'Thread created successfully.',
            ], 201);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to create threads.'], 403);
        } catch (ForumException $e) {
            Log::error('Failed to create forum thread', ['error' => $e->getMessage()]);
            // Map plan/quota errors to 403 Forbidden as expected by tests
            $statusCode = (str_contains($e->getMessage(), 'subscription') || str_contains($e->getMessage(), 'limit') || str_contains($e->getMessage(), 'plan')) ? 403 : 422;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to create forum thread', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create forum thread'], 500);
        }
    }

    /**
     * Update a thread.
     * 
     * @group Forum Threads
     * @authenticated
     * 
     * @urlParam thread string required The slug of the thread.
     * @bodyParam title string The title of the thread.
     * @bodyParam is_pinned boolean optional Pin the thread (Moderator only).
     * @bodyParam is_locked boolean optional Lock the thread (Moderator only).
     * @bodyParam county_id integer optional The ID of the county.
     * @bodyParam tag_ids array optional Array of tag IDs.
     */
    public function update(UpdateForumThreadRequest $request, ForumThread $thread): JsonResponse
    {
        try {
            $this->authorize('update', $thread);
     
            $validated = $request->validated();
     
            // Only moderators can pin/lock
            if (isset($validated['is_pinned']) || isset($validated['is_locked'])) {
                if (!auth()->user()->hasAnyRole(['admin', 'super_admin', 'moderator'])) {
                    unset($validated['is_pinned'], $validated['is_locked']);
                }
            }
     
            $updatedThread = $this->threadService->updateThread(auth()->user(), $thread, $validated);
     
            return response()->json([
                'success' => true,
                'data' => $updatedThread,
                'message' => 'Thread updated successfully.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to update this thread.'], 403);
        } catch (ForumException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update forum thread', ['error' => $e->getMessage(), 'thread_id' => $thread->id]);
            return response()->json(['success' => false, 'message' => 'Failed to update forum thread'], 500);
        }
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
        try {
            $this->authorize('delete', $thread);
     
            $this->threadService->deleteThread(auth()->user(), $thread);
     
            return response()->json(['success' => true, 'message' => 'Thread deleted successfully.']);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to delete this thread.'], 403);
        } catch (ForumException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to delete forum thread', ['error' => $e->getMessage(), 'thread_id' => $thread->id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete forum thread'], 500);
        }
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
        try {
            $this->authorize('moderate', $thread);

            $pinnedThread = $this->threadService->pinThread(auth()->user(), $thread);

            return response()->json([
                'success' => true, 
                'data' => $pinnedThread, 
                'message' => 'Thread pinned.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to pin threads.'], 403);
        } catch (ForumException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to pin forum thread', ['error' => $e->getMessage(), 'thread_id' => $thread->id]);
            return response()->json(['success' => false, 'message' => 'Failed to pin thread'], 500);
        }
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
        try {
            $this->authorize('moderate', $thread);

            $unpinnedThread = $this->threadService->unpinThread(auth()->user(), $thread);

            return response()->json([
                'success' => true, 
                'data' => $unpinnedThread, 
                'message' => 'Thread unpinned.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to unpin threads.'], 403);
        } catch (ForumException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to unpin forum thread', ['error' => $e->getMessage(), 'thread_id' => $thread->id]);
            return response()->json(['success' => false, 'message' => 'Failed to unpin thread'], 500);
        }
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
        try {
            $this->authorize('moderate', $thread);

            $lockedThread = $this->threadService->lockThread(auth()->user(), $thread);

            return response()->json([
                'success' => true, 
                'data' => $lockedThread, 
                'message' => 'Thread locked.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to lock threads.'], 403);
        } catch (ForumException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to lock forum thread', ['error' => $e->getMessage(), 'thread_id' => $thread->id]);
            return response()->json(['success' => false, 'message' => 'Failed to lock thread'], 500);
        }
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
        try {
            $this->authorize('moderate', $thread);

            $unlockedThread = $this->threadService->unlockThread(auth()->user(), $thread);

            return response()->json([
                'success' => true, 
                'data' => $unlockedThread, 
                'message' => 'Thread unlocked.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to unlock threads.'], 403);
        } catch (ForumException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to unlock forum thread', ['error' => $e->getMessage(), 'thread_id' => $thread->id]);
            return response()->json(['success' => false, 'message' => 'Failed to unlock thread'], 500);
        }
    }

}
