<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\Contracts\GroupServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * @group Admin Community Groups
 * @groupDescription Administrative endpoints for managing community groups and their memberships.
 */
class AdminGroupController extends Controller
{
    public function __construct(
        private GroupServiceContract $groupService
    ) {
        $this->middleware(['auth:sanctum', 'admin']);
    }

    /**
     * List all groups for administration.
     * 
     * Returns a paginated list of community groups with member counts.
     * 
     * @group Admin Community Groups
     * @authenticated
     * 
     * @queryParam search string Filter groups by name. Example: Technology
     * @queryParam active boolean Filter by active status. Example: true
     * @queryParam per_page integer Results per page. Example: 20
     * 
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Tech Enthusiasts",
     *       "slug": "tech-enthusiasts",
     *       "description": "A group for tech lovers",
     *       "is_active": true,
     *       "members_count": 50,
     *       "county": {"id": 1, "name": "Nairobi"}
     *     }
     *   ],
     *   "meta": {"current_page": 1, "last_page": 3, "total": 60}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Group::class);

            $filters = [
                'search' => $request->input('search'),
                'active' => $request->has('active') ? $request->boolean('active') : null,
            ];

            $groups = $this->groupService->listGroups($filters, $request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $groups->items(),
                'meta' => [
                    'current_page' => $groups->currentPage(),
                    'last_page' => $groups->lastPage(),
                    'total' => $groups->total(),
                ],
            ]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to list community groups', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve groups'], 500);
        }
    }

    /**
     * Create a new community group.
     * 
     * @group Admin Community Groups
     * @authenticated
     * 
     * @bodyParam name string required The name of the group. Example: Biotech Hub
     * @bodyParam description string The description of the group.
     * @bodyParam county_id integer required The county ID this group belongs to. Example: 1
     * @bodyParam is_active boolean Enable/disable the group. Default: true.
     * @bodyParam is_private boolean Set group as private. Default: false.
     * 
     * @response 201 {
     *   "success": true,
     *   "data": {"id": 1, "name": "Biotech Hub"},
     *   "message": "Group created successfully."
     * }
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $this->authorize('create', Group::class);

            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:groups,name',
                'description' => 'nullable|string',
                'county_id' => 'required|integer|exists:counties,id',
                'is_active' => 'boolean',
                'is_private' => 'boolean',
                'image_path' => 'nullable|string',
            ]);

            $group = $this->groupService->createGroup($validated);

            return response()->json([
                'success' => true,
                'data' => $group,
                'message' => 'Group created successfully.',
            ], 201);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create community group', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to create group: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update a group.
     * 
     * @group Admin Community Groups
     * @authenticated
     * 
     * @urlParam group integer required The group ID. Example: 1
     * @bodyParam name string The name of the group. Example: Science Club
     * @bodyParam description string The description of the group.
     * @bodyParam is_active boolean Enable/disable the group.
     * @bodyParam is_private boolean Set group as private.
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {"id": 1, "name": "Science Club"},
     *   "message": "Group updated successfully."
     * }
     */
    public function update(Request $request, Group $group): JsonResponse
    {
        try {
            $this->authorize('update', $group);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:100',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
                'is_private' => 'boolean',
            ]);

            $updatedGroup = $this->groupService->updateGroup($group, $validated);

            return response()->json([
                'success' => true,
                'data' => $updatedGroup,
                'message' => 'Group updated successfully.',
            ]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update community group', [
                'error' => $e->getMessage(),
                'group_id' => $group->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to update group'], 500);
        }
    }

    /**
     * Delete a group.
     * 
     * @group Admin Community Groups
     * @authenticated
     * 
     * @urlParam group integer required The group ID. Example: 1
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Group deleted successfully."
     * }
     */
    public function destroy(Group $group): JsonResponse
    {
        try {
            $this->authorize('delete', $group);

            $this->groupService->deleteGroup($group);

            return response()->json([
                'success' => true,
                'message' => 'Group deleted successfully.',
            ]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to delete community group', [
                'error' => $e->getMessage(),
                'group_id' => $group->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to delete group'], 500);
        }
    }

    /**
     * List group members for management.
     * 
     * @group Admin Community Groups
     * @authenticated
     * 
     * @urlParam group integer required The group ID. Example: 1
     * @queryParam per_page integer Results per page. Example: 20
     * 
     * @response 200 {
     *   "success": true,
     *   "data": [{"id": 1, "username": "jane_doe"}],
     *   "meta": {"total": 100}
     * }
     */
    public function members(Request $request, Group $group): JsonResponse
    {
        try {
            $this->authorize('manageMembers', $group);

            $members = $this->groupService->listMembers($group, $request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $members->items(),
                'meta' => [
                    'current_page' => $members->currentPage(),
                    'last_page' => $members->lastPage(),
                    'total' => $members->total(),
                ],
            ]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to list group members', [
                'error' => $e->getMessage(),
                'group_id' => $group->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve members'], 500);
        }
    }

    /**
     * Remove a member from a group.
     * 
     * @group Admin Community Groups
     * @authenticated
     * 
     * @urlParam group integer required The group ID. Example: 1
     * @urlParam userId integer required The user ID to remove. Example: 5
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Member removed successfully."
     * }
     */
    public function removeMember(Request $request, Group $group, int $userId): JsonResponse
    {
        try {
            $this->authorize('manageMembers', $group);

            $this->groupService->removeMember($group, $userId);

            return response()->json([
                'success' => true,
                'message' => 'Member removed successfully.',
            ]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to remove group member', [
                'error' => $e->getMessage(), 
                'group_id' => $group->id,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to remove member'], 500);
        }
    }
}
