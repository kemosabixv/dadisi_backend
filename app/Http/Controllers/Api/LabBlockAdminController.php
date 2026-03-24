<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CreateMaintenanceBlockDTO;
use App\DTOs\UpdateMaintenanceBlockDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\LabMaintenanceBlockResource;
use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use App\Models\LabSpace;
use App\Notifications\BookingRescheduledNotification;
use App\Notifications\BookingRescheduleNeededNotification;
use App\Services\Contracts\LabBookingServiceContract;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * @group Lab Space Admin
 *
 * Manage lab space maintenance blocks, holidays, and closures for admins/supervisors
 */
class LabBlockAdminController extends Controller
{
    public function __construct(private LabBookingServiceContract $bookingService) {}

    /**
     * Create a maintenance, holiday, or closure block
     *
     * @bodyParam block_type string required Type of block: maintenance, holiday, or closure. Example: maintenance
     * @bodyParam title string required Title of the block. Example: Equipment Servicing
     * @bodyParam reason string nullable Reason for the block. Example: Annual PCR calibration
     * @bodyParam starts_at string required Start datetime in ISO 8601 format. Example: 2026-03-15T08:00:00Z
     * @bodyParam ends_at string required End datetime in ISO 8601 format. Example: 2026-03-15T18:00:00Z
     *
     * @response 201 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "lab_space_id": 2,
     *     "title": "Equipment Servicing",
     *     "reason": "Annual PCR calibration",
     *     "block_type": "maintenance",
     *     "starts_at": "2026-03-15T08:00:00Z",
     *     "ends_at": "2026-03-15T18:00:00Z",
     *     "created_by": 1,
     *     "created_at": "2026-03-01T12:00:00Z"
     *   },
     *   "rescheduled": [
     *     {
     *       "booking_id": 5,
     *       "user": "john_doe",
     *       "original_start": "2026-03-15T10:00:00Z",
     *       "new_start": "2026-03-16T10:00:00Z"
     *     }
     *   ]
     * }
     */
    public function store(Request $request, LabSpace $space): JsonResponse
    {
        // Check user can create maintenance blocks
        $this->authorize('create', LabMaintenanceBlock::class);

        // Check user is assigned to this lab (for lab_supervisor)
        if (auth()->user()->hasRole('lab_supervisor')) {
            if (!auth()->user()->assignedLabSpaces()->where('lab_spaces.id', $space->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not assigned to this lab space',
                ], 403);
            }
        }

        $validated = $request->validate([
            'block_type' => 'required|in:maintenance,holiday,closure',
            'title' => 'required|string|max:255',
            'reason' => 'nullable|string',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
        ]);

        $dto = CreateMaintenanceBlockDTO::fromArray(array_merge(
            $validated,
            ['lab_space_id' => $space->id]
        ));
        
        // Create the block
        $block = LabMaintenanceBlock::create(array_merge(
            $dto->toArray(),
            ['created_by' => auth()->id()]
        ));

        // Find and reschedule conflicting bookings
        $rescheduled = $this->rescheduleConflictingBookings($space, $block);

        return response()->json([
            'success' => true,
            'data' => new LabMaintenanceBlockResource($block->load('creator')),
            'rescheduled' => $rescheduled,
        ], 201);
    }

    /**
     * Get all blocks for a lab space
     *
     * @queryParam start string Start date filter in ISO 8601 format. Example: 2026-03-01T00:00:00Z
     * @queryParam end string End date filter in ISO 8601 format. Example: 2026-03-31T23:59:59Z
     * @queryParam block_type string Filter by type: maintenance, holiday, closure. Example: maintenance
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "lab_space_id": 2,
     *       "title": "Equipment Servicing",
     *       "block_type": "maintenance",
     *       "starts_at": "2026-03-15T08:00:00Z",
     *       "ends_at": "2026-03-15T18:00:00Z",
     *       "created_by": 1,
     *       "creator": {"id": 1, "username": "admin"}
     *     }
     *   ]
     * }
     */
    public function index(Request $request, LabSpace $space): JsonResponse
    {
        // Check user can manage this lab's maintenance blocks
        if (auth()->user()->hasRole('lab_supervisor')) {
            if (!auth()->user()->assignedLabSpaces()->where('lab_spaces.id', $space->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not assigned to this lab space',
                ], 403);
            }
        }

        try {
            $query = LabMaintenanceBlock::where('lab_space_id', $space->id);

            // Filter by date range
            if ($request->has('start')) {
                $query->where('starts_at', '>=', Carbon::parse($request->start));
            }
            if ($request->has('end')) {
                $query->where('ends_at', '<=', Carbon::parse($request->end));
            }

            // Filter by block type
            if ($request->has('block_type')) {
                $query->where('block_type', $request->block_type);
            }

            $blocks = $query->with('creator')->orderBy('starts_at')->get();

            return response()->json([
                'success' => true,
                'data' => LabMaintenanceBlockResource::collection($blocks),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to retrieve lab blocks', [
                'error' => $e->getMessage(),
                'space_id' => $space->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve blocks',
            ], 500);
        }
    }

    /**
     * Update a maintenance block
     *
     * @bodyParam block_type string Type of block: maintenance, holiday, or closure. Example: maintenance
     * @bodyParam title string Title of the block. Example: Equipment Servicing
     * @bodyParam reason string nullable Reason for the block. Example: Annual calibration
     * @bodyParam starts_at string Start datetime in ISO 8601 format. Example: 2026-03-15T08:00:00Z
     * @bodyParam ends_at string End datetime in ISO 8601 format. Example: 2026-03-15T18:00:00Z
     *
     * @response 200 {
     *   "success": true,
     *   "data": {...}
     * }
     */
    public function update(Request $request, LabSpace $space, LabMaintenanceBlock $block): JsonResponse
    {
        if ($block->lab_space_id !== $space->id) {
            return response()->json([
                'success' => false,
                'message' => 'Block not found for this space',
            ], 404);
        }

        $this->authorize('update', $block);

        $validated = $request->validate([
            'block_type' => 'sometimes|in:maintenance,holiday,closure',
            'title' => 'sometimes|string|max:255',
            'reason' => 'nullable|string',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date|after:starts_at',
        ]);

        $dto = UpdateMaintenanceBlockDTO::fromArray($validated);
        $data = array_filter($dto->toArray(), fn($v) => $v !== null);
        
        // If dates changed, reschedule affected bookings
        if (isset($data['starts_at']) || isset($data['ends_at'])) {
            $oldBlock = clone $block;
            $block->update($data);
            
            // Find new conflicts
            $rescheduled = $this->rescheduleConflictingBookings($space, $block, $oldBlock);
        } else {
            $block->update($data);
            $rescheduled = [];
        }

        return response()->json([
            'success' => true,
            'data' => new LabMaintenanceBlockResource($block->fresh()->load('creator')),
            'rescheduled' => $rescheduled,
        ]);
    }

    /**
     * Delete a maintenance block
     *
     * @response 204
     */
    public function destroy(LabSpace $space, LabMaintenanceBlock $block): JsonResponse
    {
        if ($block->lab_space_id !== $space->id) {
            return response()->json([
                'success' => false,
                'message' => 'Block not found for this space',
            ], 404);
        }

        $this->authorize('delete', $block);

        $block->delete();

        return response()->json([
            'success' => true,
            'message' => 'Block deleted successfully',
        ], 204);
    }

    /**
     * Find and reschedule bookings conflicting with the block
     */
    private function rescheduleConflictingBookings(
        LabSpace $space,
        LabMaintenanceBlock $block,
        ?LabMaintenanceBlock $oldBlock = null
    ): array {
        // Find conflicting bookings
        $conflicts = LabBooking::where('lab_space_id', $space->id)
            ->where('starts_at', '<', $block->ends_at)
            ->where('ends_at', '>', $block->starts_at)
            ->whereNotIn('status', [LabBooking::STATUS_CANCELLED, LabBooking::STATUS_REJECTED])
            ->get();

        $rescheduled = [];

        foreach ($conflicts as $booking) {
            $alternativeSlot = $this->bookingService->findAlternativeSlot(
                spaceId: $space->id,
                durationHours: $booking->duration_hours,
                blockToAvoid: $block,
                excludeBookingId: $booking->id
            );

            if ($alternativeSlot) {
                $oldStartsAt = $booking->starts_at;
                $oldEndsAt = $booking->ends_at;

                // Update booking with new slot
                $booking->update([
                    'starts_at' => $alternativeSlot['starts_at'],
                    'ends_at' => $alternativeSlot['ends_at'],
                ]);

                // Send notification (implemented separately)
                $this->notifyBookingRescheduled($booking, $oldStartsAt, $oldEndsAt, $block);

                $rescheduled[] = [
                    'booking_id' => $booking->id,
                    'user' => $booking->user?->username,
                    'original_start' => $oldStartsAt->toISOString(),
                    'new_start' => $booking->starts_at->toISOString(),
                ];
            } else {
                // Unable to reschedule - notify user to contact support
                $this->notifyBookingRescheduleNeeded($booking, $block);

                $rescheduled[] = [
                    'booking_id' => $booking->id,
                    'user' => $booking->user?->username,
                    'status' => 'needs_manual_intervention',
                    'reason' => 'Unable to auto-reschedule. User notified.',
                ];
            }
        }

        return $rescheduled;
    }

    /**
     * Send notification that booking was rescheduled
     */
    private function notifyBookingRescheduled(
        LabBooking $booking,
        Carbon $oldStartsAt,
        Carbon $oldEndsAt,
        LabMaintenanceBlock $block
    ): void
    {
        if ($booking->user) {
            Notification::send(
                $booking->user,
                new BookingRescheduledNotification(
                    booking: $booking,
                    oldStartsAt: $oldStartsAt,
                    oldEndsAt: $oldEndsAt,
                    block: $block
                )
            );
        }
    }

    /**
     * Send notification that manual rescheduling is needed
     */
    private function notifyBookingRescheduleNeeded(
        LabBooking $booking,
        LabMaintenanceBlock $block
    ): void
    {
        if ($booking->user) {
            Notification::send(
                $booking->user,
                new BookingRescheduleNeededNotification(
                    booking: $booking,
                    block: $block
                )
            );
        }
    }
}
