<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminGroupController extends Controller
{
    /**
     * List all groups for administration.
     * 
     * @group Admin Forum
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Group::class);

        $query = Group::with('county')->withCount('members');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $groups = $query->orderBy('name')->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $groups->items(),
            'meta' => [
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'total' => $groups->total(),
            ],
        ]);
    }

    /**
     * Update a group.
     * 
     * @group Admin Forum
     * @authenticated
     */
    public function update(Request $request, Group $group): JsonResponse
    {
        $this->authorize('update', $group);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_private' => 'boolean',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $group->update($validated);

        return response()->json([
            'data' => $group,
            'message' => 'Group updated successfully.',
        ]);
    }

    /**
     * Delete a group.
     * 
     * @group Admin Forum
     * @authenticated
     */
    public function destroy(Group $group): JsonResponse
    {
        $this->authorize('delete', $group);

        $group->delete();

        return response()->json([
            'message' => 'Group deleted successfully.',
        ]);
    }

    /**
     * List group members for management.
     * 
     * @group Admin Forum
     * @authenticated
     */
    public function members(Request $request, Group $group): JsonResponse
    {
        $this->authorize('manageMembers', $group);

        $members = $group->members()
            ->with('memberProfile:user_id,first_name,last_name,profile_picture_path')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $members->items(),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'total' => $members->total(),
            ],
        ]);
    }

    /**
     * Remove a member from a group.
     * 
     * @group Admin Forum
     * @authenticated
     */
    public function removeMember(Request $request, Group $group, int $userId): JsonResponse
    {
        $this->authorize('manageMembers', $group);

        $group->members()->detach($userId);
        $group->updateMemberCount();

        return response()->json([
            'message' => 'Member removed successfully.',
        ]);
    }
}
