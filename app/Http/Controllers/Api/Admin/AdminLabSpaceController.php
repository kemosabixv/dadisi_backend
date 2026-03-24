<?php

namespace App\Http\Controllers\Api\Admin;

use App\DTOs\CreateLabSpaceDTO;
use App\DTOs\UpdateLabSpaceDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\LabSpaceResource;
use App\Models\LabSpace;
use App\Services\Contracts\LabManagementServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @group Admin - Lab Spaces
 *
 * Admin endpoints for managing lab spaces.
 * @authenticated
 */
class AdminLabSpaceController extends Controller
{
    protected LabManagementServiceContract $labService;

    public function __construct(LabManagementServiceContract $labService)
    {
        $this->labService = $labService;
        $this->middleware('can:manage_lab_spaces')->only(['store', 'destroy']);
        $this->middleware('can:edit_lab_space')->only(['update']);
    }

    /**
     * List all lab spaces (paginated).
     *
     * @queryParam type string Filter by space type. Example: wet_lab
     * @queryParam active boolean Filter by active status. Example: true
     * @queryParam per_page integer Items per page. Default: 15. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": {...},
     *   "meta": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 4}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LabSpace::class);

        $query = LabSpace::query();

        if ($request->has('status')) {
            $status = $request->status;
            if ($status === 'open') {
                $query->whereDoesntHave('maintenanceBlocks', function ($q) {
                    $q->where('starts_at', '<=', now())
                      ->where('ends_at', '>=', now());
                });
            } elseif (in_array($status, ['under_maintenance', 'holiday', 'temporarily_closed'])) {
                $type = match($status) {
                    'under_maintenance' => 'maintenance',
                    'holiday' => 'holiday',
                    'temporarily_closed' => 'closure',
                };
                $query->whereHas('maintenanceBlocks', function ($q) use ($type) {
                    $q->where('block_type', $type)
                      ->where('starts_at', '<=', now())
                      ->where('ends_at', '>=', now());
                });
            }
        }

        if ($request->has('bookings_status')) {
            $query->where('bookings_enabled', $request->bookings_status === 'enabled');
        }

        if ($request->has('active')) {
            $query->where('is_available', $request->boolean('active'));
        }

        // Scope to assigned spaces for supervisors
        if ($request->user()->hasRole('lab_supervisor')) {
            $query->whereIn('id', $request->user()->assignedLabSpaces()->pluck('lab_spaces.id'));
        }

        $perPage = $request->input('per_page', 50);
        $spaces = $query
            ->with('media')
            ->withCount([
                'bookings as active_bookings_count' => function ($q) {
                    $q->whereIn('status', ['confirmed', 'in_progress']);
                },
            ])
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $spaces->map(function ($space) {
                return new LabSpaceResource($space);
            }),
            'meta' => [
                'current_page' => $spaces->currentPage(),
                'last_page' => $spaces->lastPage(),
                'per_page' => $spaces->perPage(),
                'total' => $spaces->total(),
            ],
        ]);
    }

    /**
     * Create a new lab space.
     *
     * @bodyParam name string required The name of the lab space. Example: Wet Lab
     * @bodyParam type string required The type (wet_lab, dry_lab, greenhouse, mobile_lab). Example: wet_lab
     * @bodyParam description string Description of the lab space. Example: Fully equipped wet laboratory...
     * @bodyParam capacity integer Maximum capacity. Default: 4. Example: 6
     * @bodyParam equipment_list array List of equipment. Example: ["fume_hood", "pcr_machine"]
     * @bodyParam safety_requirements array Required certifications. Example: ["lab_safety_training"]
     * @bodyParam is_available boolean Whether space is available for booking. Example: true
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Lab space created successfully",
     *   "data": {...}
     * }
     */
    public function store(\App\Http\Requests\Api\CreateLabSpaceRequest $request): JsonResponse
    {
        $this->authorize('create', LabSpace::class);
        
        $dto = CreateLabSpaceDTO::fromArray($request->validated());
        $space = $this->labService->createLabSpace(auth()->user(), $dto);

        return response()->json([
            'success' => true,
            'message' => 'Lab space created successfully',
            'data' => new LabSpaceResource($space->load('media')),
        ], 201);
    }

    /**
     * Get lab space details.
     *
     * @urlParam id integer required The lab space ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {...}
     * }
     */
    public function show(int $id): JsonResponse
    {
        $space = LabSpace::with('media')->findOrFail($id);
        $this->authorize('view', $space);
        $space->loadCount('bookings');

        return response()->json([
            'success' => true,
            'data' => new LabSpaceResource($space),
        ]);
    }

    /**
     * Update a lab space.
     *
     * @urlParam id integer required The lab space ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Lab space updated successfully",
     *   "data": {...}
     * }
     */
    public function update(\App\Http\Requests\Api\UpdateLabSpaceRequest $request, int $id): JsonResponse
    {
        $space = LabSpace::findOrFail($id);
        $this->authorize('update', $space);
        
        $dto = UpdateLabSpaceDTO::fromArray($request->validated());
        $space = $this->labService->updateLabSpace(auth()->user(), $space, $dto);

        return response()->json([
            'success' => true,
            'message' => 'Lab space updated successfully',
            'data' => new LabSpaceResource($space->fresh()->load('media')),
        ]);
    }

    /**
     * Delete a lab space (soft delete).
     *
     * @urlParam id integer required The lab space ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Lab space deleted successfully"
     * }
     */
    /**
     * Get lab spaces supervised by the current user
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {"id": 1, "name": "Wet Lab", "slug": "wet-lab", ...}
     *   ]
     * }
     */
    public function mySupervisedLabs(): JsonResponse
    {
        $user = auth()->user();
        
        // Super admin can see all labs
        if ($user->hasRole('super_admin')) {
            $labs = LabSpace::select('id', 'name', 'slug', 'type', 'capacity', 'is_available')
                ->where('is_available', true)
                ->orderBy('name')
                ->get();
        } else {
            // Lab supervisors can only see their assigned labs
            $labs = $user->assignedLabSpaces()
                ->select('lab_spaces.id', 'lab_spaces.name', 'lab_spaces.slug', 'lab_spaces.type', 'lab_spaces.capacity', 'lab_spaces.is_available')
                ->where('lab_spaces.is_available', true)
                ->orderBy('lab_spaces.name')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $labs,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $space = LabSpace::findOrFail($id);

        $this->authorize('delete', $space);

        $space->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lab space deleted successfully',
        ]);
    }

    /**
     * Get all lab supervisor assignments (paginated).
     *
     * @queryParam lab_space_id integer Filter by lab space. Example: 1
     * @queryParam per_page integer Items per page. Default: 15. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {"id": 1, "lab_space_id": 1, "user_id": 2, "user_name": "John Doe", "lab_name": "Wet Lab", "assigned_at": "2024-01-15"}
     *   ],
     *   "meta": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1}
     * }
     */
    public function listAssignments(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LabSpace::class);

        $query = LabSpace::query()
            ->with('supervisors')
            ->select('lab_spaces.id', 'lab_spaces.name');

        // Only admin and super_admin can manage assignments
        if (!$request->user()->hasAnyRole(['super_admin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to manage lab supervisor assignments',
            ], 403);
        }

        $perPage = $request->input('per_page', 15);
        $spaces = $query->paginate($perPage);

        // Flatten the response to show individual assignments
        $assignments = [];
        foreach ($spaces as $space) {
            foreach ($space->supervisors as $supervisor) {
                $assignments[] = [
                    'id' => $supervisor->pivot->id ?? "{$space->id}-{$supervisor->id}",
                    'lab_space_id' => $space->id,
                    'lab_name' => $space->name,
                    'user_id' => $supervisor->id,
                    'user_name' => $supervisor->name,
                    'user_email' => $supervisor->email,
                    'assigned_at' => $supervisor->pivot->assigned_at ?? null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $assignments,
            'meta' => [
                'current_page' => $spaces->currentPage(),
                'last_page' => $spaces->lastPage(),
                'per_page' => $spaces->perPage(),
                'total' => count($assignments),
            ],
        ]);
    }

    /**
     * Assign a supervisor to a lab space.
     *
     * @bodyParam lab_space_id integer required The lab space ID. Example: 1
     * @bodyParam user_id integer required The user ID (must have lab_supervisor role). Example: 2
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Supervisor assigned successfully",
     *   "data": {"id": 1, "lab_space_id": 1, "user_id": 2, "assigned_at": "2024-01-15"}
     * }
     */
    public function assignSupervisor(Request $request): JsonResponse
    {
        // Only admin and super_admin can assign supervisors
        if (!$request->user()->hasAnyRole(['super_admin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to assign supervisors',
            ], 403);
        }

        $validated = $request->validate([
            'lab_space_id' => 'required|integer|exists:lab_spaces,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $space = LabSpace::findOrFail($validated['lab_space_id']);
        $user = \App\Models\User::findOrFail($validated['user_id']);

        // Verify user has lab_supervisor role
        if (!$user->hasRole('lab_supervisor')) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have lab_supervisor role',
            ], 422);
        }

        // Check if already assigned
        if ($space->supervisors()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Supervisor is already assigned to this lab space',
            ], 422);
        }

        // Assign supervisor
        $space->supervisors()->attach($user->id, ['assigned_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Supervisor assigned successfully',
            'data' => [
                'lab_space_id' => $space->id,
                'lab_name' => $space->name,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'assigned_at' => now(),
            ],
        ], 201);
    }

    /**
     * Remove a supervisor from a lab space.
     *
     * @urlParam lab_space_id integer required The lab space ID. Example: 1
     * @urlParam user_id integer required The user ID. Example: 2
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Supervisor removed successfully"
     * }
     */
    public function removeSupervisor(int $labSpaceId, int $userId, Request $request): JsonResponse
    {
        // Only admin and super_admin can remove supervisors
        if (!$request->user()->hasAnyRole(['super_admin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to remove supervisors',
            ], 403);
        }

        $space = LabSpace::findOrFail($labSpaceId);
        $user = \App\Models\User::findOrFail($userId);

        // Check if assignment exists
        if (!$space->supervisors()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Supervisor is not assigned to this lab space',
            ], 404);
        }

        // Remove supervisor
        $space->supervisors()->detach($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Supervisor removed successfully',
        ]);
    }

    /**
     * Disable new bookings for a lab space.
     *
     * Prevents new member bookings from being created for this lab.
     * Existing bookings, lab operations (maintenance, attendance), and guest cancellation
     * links remain functional.
     *
     * @urlParam id integer required The lab space ID. Example: 1
     * @bodyParam reason string optional Reason for disabling bookings. Example: Maintenance scheduled
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Lab bookings disabled successfully",
     *   "data": {"id": 1, "name": "Wet Lab", "bookings_enabled": false}
     * }
     */
    public function disableBookings(Request $request, int $id): JsonResponse
    {
        $space = LabSpace::findOrFail($id);
        $this->authorize('disableBookings', $space);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        // Update the lab to disable bookings
        $space->update(['bookings_enabled' => false]);

        // Log the action to audit trail
        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'lab_bookings_disabled',
            'model_type' => LabSpace::class,
            'model_id' => $space->id,
            'changes' => [
                'bookings_enabled' => [
                    'from' => true,
                    'to' => false,
                    'reason' => $validated['reason'] ?? null,
                ],
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Notify supervisors and managers
        // TODO: Send notification to assigned supervisors

        return response()->json([
            'success' => true,
            'message' => 'Lab bookings disabled successfully',
            'data' => [
                'id' => $space->id,
                'name' => $space->name,
                'bookings_enabled' => $space->bookings_enabled,
            ],
        ]);
    }

    /**
     * Enable new bookings for a lab space.
     *
     * Allows new member bookings to be created for this lab again.
     *
     * @urlParam id integer required The lab space ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Lab bookings enabled successfully",
     *   "data": {"id": 1, "name": "Wet Lab", "bookings_enabled": true}
     * }
     */
    public function enableBookings(Request $request, int $id): JsonResponse
    {
        $space = LabSpace::findOrFail($id);
        $this->authorize('enableBookings', $space);

        // Update the lab to enable bookings
        $space->update(['bookings_enabled' => true]);

        // Log the action to audit trail
        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'lab_bookings_enabled',
            'model_type' => LabSpace::class,
            'model_id' => $space->id,
            'changes' => [
                'bookings_enabled' => [
                    'from' => false,
                    'to' => true,
                ],
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Notify supervisors and managers
        // TODO: Send notification to assigned supervisors

        return response()->json([
            'success' => true,
            'message' => 'Lab bookings enabled successfully',
            'data' => [
                'id' => $space->id,
                'name' => $space->name,
                'bookings_enabled' => $space->bookings_enabled,
            ],
        ]);
    }
}
