<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\GroupServiceContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group County Groups
 *
 * @groupDescription API endpoints for managing county-based community groups and memberships.
 *
 * Groups are automatically generated from counties and serve as local hubs for discussions.
 * Users can join groups to show their affiliation and filter discussions.
 */
class GroupController extends Controller
{
    public function __construct(private GroupServiceContract $groupService) {}

    /**
     * Store Group
     * 
     * @group County Groups
     * @authenticated
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // Staff only check - using simple gate/role check for now
            if (!$request->user()->is_staff && !$request->user()->hasRole('super_admin')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized. Only staff can create groups.'], 403);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:groups,name',
                'description' => 'required|string|max:1000',
                'county_id' => 'nullable|exists:counties,id',
                'is_private' => 'boolean',
                'is_active' => 'boolean',
            ]);

            $dto = new \App\DTOs\CreateGroupDTO($validated);
            $group = $this->groupService->createGroup($dto);

            return response()->json(['success' => true, 'data' => $group], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create group', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create group'], 500);
        }
    }

    /**
     * Update Group
     * 
     * @group County Groups
     * @authenticated
     */
    public function update(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        try {
            $group = \App\Models\Group::where('slug', $slug)->firstOrFail();

            if (!$request->user()->is_staff && !$request->user()->hasRole('super_admin')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized. Only staff can update groups.'], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:100|unique:groups,name,' . $group->id,
                'description' => 'sometimes|required|string|max:1000',
                'county_id' => 'nullable|exists:counties,id',
                'is_private' => 'boolean',
                'is_active' => 'boolean',
            ]);

            $dto = new \App\DTOs\UpdateGroupDTO($validated);
            $updatedGroup = $this->groupService->updateGroup($group, $dto);

            return response()->json(['success' => true, 'data' => $updatedGroup]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Group not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to update group', ['error' => $e->getMessage(), 'slug' => $slug]);
            return response()->json(['success' => false, 'message' => 'Failed to update group'], 500);
        }
    }

    /**
     * Delete Group
     * 
     * @group County Groups
     * @authenticated
     */
    public function destroy(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        try {
            $group = \App\Models\Group::where('slug', $slug)->firstOrFail();

            if (!$request->user()->hasRole('super_admin')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized. Only admins can delete groups.'], 403);
            }

            $this->groupService->deleteGroup($group);

            return response()->json(['success' => true, 'message' => 'Group deleted successfully.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Group not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete group', ['error' => $e->getMessage(), 'slug' => $slug]);
            return response()->json(['success' => false, 'message' => 'Failed to delete group'], 500);
        }
    }

    /**
     * List all county groups.
     *
     * Returns a paginated list of active county groups, ordered by member count.
     * Supports filtering by county ID and searching by name.
     *
     * @group County Groups
     *
     * @unauthenticated
     *
     * @queryParam county_id integer Filter groups by specific county ID. Example: 1
     * @queryParam search string Search for groups by name. Example: Nairobi
     * @queryParam per_page integer The number of items per page. Example: 15
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Nairobi Community",
     *       "slug": "nairobi-community",
     *       "description": "Connect with members from Nairobi county.",
     *       "county_id": 1,
     *       "county": { "id": 1, "name": "Nairobi" },
     *       "member_count": 124,
     *       "is_member": false
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 3,
     *     "total": 45
     *   }
     * }
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $userId = $request->user()?->id;
            $filters = $request->only(['search', 'county_id', 'sort', 'order']);
            $perPage = $request->input('per_page', 15);

            $groups = $this->groupService->listGroups($filters, (int)$perPage, $userId);

            return response()->json([
                'data' => $groups->items(),
                'meta' => [
                    'current_page' => $groups->currentPage(),
                    'last_page' => $groups->lastPage(),
                    'per_page' => $groups->perPage(),
                    'total' => $groups->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve groups', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to retrieve groups'], 500);
        }
    }

    /**
     * Get group details
     *
     * Get detailed information about a group, including members and related discussions.
     *
     * @urlParam slug string required The group slug. Example: nairobi-community
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Nairobi Community Hub",
     *     "slug": "nairobi-community-hub",
     *     "description": "The primary hub for biotech enthusiasts in Nairobi county.",
     *     "county": { "id": 1, "name": "Nairobi" },
     *     "member_count": 150,
     *     "is_member": true,
     *     "members": [
     *       {"id": 1, "username": "superadmin", "profile_picture": null, "joined_at": "2025-01-01"}
     *     ],
     *     "recent_discussions": [
     *       {"id": 1, "title": "New Equipment in Nairobi Hub", "user": {"id": 1, "username": "superadmin"}}
     *     ]
     *   }
     * }
     */
    public function show(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        try {
            $group = \App\Models\Group::where('slug', $slug)->active()->firstOrFail();
            $group->load(['county', 'members' => function ($query) {
                $query->limit(20)->with('memberProfile:user_id,first_name,last_name');
            }]);

            $userId = $request->user()?->id;
            $membership = $userId
                ? $group->members()->where('user_id', $userId)->first()
                : null;

            $group->is_member = $membership && $membership->pivot->status === 'active';
            $group->membership_status = $membership ? $membership->pivot->status : null;

            // Get recent discussions associated with this group
            $recentDiscussions = $group->forumThreads()
                ->with(['user:id,username', 'category:id,name,slug'])
                ->latest()
                ->limit(10)
                ->get();

            // Count threads for this group
            $threadCount = $group->forumThreads()->count();

            return response()->json([
                'data' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'slug' => $group->slug,
                    'description' => $group->description,
                    'image_path' => $group->image_path,
                    'county' => $group->county,
                    'county_id' => $group->county_id,
                    'member_count' => $group->member_count,
                    'thread_count' => $threadCount,
                    'is_member' => $group->is_member,
                    'membership_status' => $group->membership_status,
                    'members' => $group->members->map(fn ($m) => [
                        'id' => $m->id,
                        'username' => $m->username,
                        'profile_picture' => $m->profile_picture_path,
                        'status' => $m->pivot->status,
                        'joined_at' => $m->pivot->joined_at,
                    ]),
                    'recent_discussions' => $recentDiscussions,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Group not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve group', ['error' => $e->getMessage(), 'slug' => $slug]);

            return response()->json(['success' => false, 'message' => 'Failed to retrieve group'], 500);
        }
    }

    /**
     * Join a group
     *
     * Join a community group as a member.
     *
     * @authenticated
     *
     * @urlParam slug string required The group slug. Example: nairobi-community
     *
     * @response 200 { "message": "Successfully joined the group." }
     * @response 422 { "message": "You are already a member of this group." }
     */
    public function join(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        try {
            $group = \App\Models\Group::where('slug', $slug)->active()->firstOrFail();
            $user = $request->user();

            $status = $this->groupService->joinGroup($group, $user);

            if ($status === 'pending') {
                return response()->json(['message' => 'Join request sent. Waiting for approval.', 'status' => 'pending']);
            }

            return response()->json(['message' => 'Successfully joined the group.', 'status' => 'active']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Group not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to join group', ['error' => $e->getMessage(), 'slug' => $slug, 'user_id' => $request->user()->id]);

            return response()->json(['success' => false, 'message' => 'Failed to join group'], 500);
        }
    }

    /**
     * Leave a group
     *
     * Leave a community group.
     *
     * @authenticated
     *
     * @urlParam slug string required The group slug. Example: nairobi-community
     *
     * @response 200 { "message": "Successfully left the group." }
     * @response 422 { "message": "You are not a member of this group." }
     */
    public function leave(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        try {
            $group = \App\Models\Group::where('slug', $slug)->active()->firstOrFail();
            $user = $request->user();

            if (! $group->hasMember($user)) {
                return response()->json(['message' => 'You are not a member of this group.'], 422);
            }

            $this->groupService->leaveGroup($group, $user);

            return response()->json(['message' => 'Successfully left the group.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Group not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to leave group', ['error' => $e->getMessage(), 'slug' => $slug, 'user_id' => $request->user()->id]);

            return response()->json(['success' => false, 'message' => 'Failed to leave group'], 500);
        }
    }

    /**
     * List group members
     *
     * Get paginated members of a group.
     *
     * @urlParam slug string required The group slug. Example: nairobi-community
     *
     * @queryParam per_page integer Items per page. Example: 20
     *
     * @response 200 {
     *   "data": [
     *     { "id": 1, "username": "john_doe", "role": "member", "joined_at": "2024-01-15" }
     *   ]
     * }
     */
    public function members(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        try {
            $group = \App\Models\Group::where('slug', $slug)->active()->firstOrFail();
            $perPage = $request->input('per_page', 20);

            $members = $this->groupService->listMembers($group, $perPage);

            return response()->json([
                'data' => $members->items(),
                'meta' => [
                    'current_page' => $members->currentPage(),
                    'last_page' => $members->lastPage(),
                    'total' => $members->total(),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Group not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve group members', ['error' => $e->getMessage(), 'slug' => $slug]);

            return response()->json(['success' => false, 'message' => 'Failed to retrieve group members'], 500);
        }
    }
}
