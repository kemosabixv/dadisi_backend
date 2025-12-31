<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Services\Contracts\LabBookingServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * @group Lab Bookings
 *
 * Endpoints for managing user lab space bookings.
 * @authenticated
 */
class LabBookingController extends Controller
{
    public function __construct(private LabBookingServiceContract $bookingService) {}

    /**
     * Get user's quota status.
     *
     * Returns the user's lab hours quota, usage, and remaining hours.
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "has_access": true,
     *     "plan_name": "Premium Member",
     *     "limit": 20,
     *     "unlimited": false,
     *     "used": 4.0,
     *     "remaining": 16.0,
     *     "resets_at": "2026-01-31T23:59:59Z"
     *   }
     * }
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "has_access": false,
     *     "reason": "plan_not_eligible"
     *   }
     * }
     */
    public function quotaStatus(): JsonResponse
    {
        try {
            $status = $this->bookingService->getQuotaStatus(Auth::user());

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to retrieve quota status', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve quota status'], 500);
        }
    }

    /**
     * List user's bookings.
     *
     * @queryParam status string Filter by status (pending, approved, rejected, cancelled, completed, no_show). Example: approved
     * @queryParam upcoming boolean Only show upcoming bookings. Example: true
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "Bioinformatics Workshop",
     *       "purpose": "Training session for genomics tools",
     *       "starts_at": "2025-02-15T09:00:00Z",
     *       "ends_at": "2025-02-15T12:00:00Z",
     *       "duration_hours": 3,
     *       "slot_type": "hourly",
     *       "status": "approved",
     *       "lab_space": {"id": 1, "name": "Nairobi Central Wet Lab"}
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = LabBooking::forUser($user->id)->with('labSpace');

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Only upcoming
            if ($request->boolean('upcoming')) {
                $query->upcoming();
            }

            $bookings = $query->orderBy('starts_at', 'desc')->get();

            // Add computed attributes
            $bookings->each(function ($booking) {
                $booking->append(['duration_hours', 'is_cancellable', 'status_color']);
            });

            return response()->json([
                'success' => true,
                'data' => $bookings,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to retrieve bookings', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve bookings'], 500);
        }
    }

    /**
     * Create a new booking.
     *
     * Creates a booking request. Premium/Corporate plan members are auto-approved.
     * Student plan members require admin approval.
     *
     * @bodyParam lab_space_id integer required The ID of the lab space. Example: 1
     * @bodyParam starts_at string required Start time in ISO 8601 format. Example: 2024-01-15T09:00:00Z
     * @bodyParam ends_at string required End time in ISO 8601 format. Example: 2024-01-15T12:00:00Z
     * @bodyParam purpose string required Description of what you'll be doing. Example: DNA amplification experiment
     * @bodyParam title string Optional title for the booking. Example: PCR Experiment
     * @bodyParam slot_type string Slot type (hourly, half_day, full_day). Default: hourly. Example: hourly
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Booking created successfully",
     *   "data": {
     *     "id": 1,
     *     "status": "approved",
     *     "lab_space": {...}
     *   }
     * }
     * @response 422 {
     *   "success": false,
     *   "message": "You have 2h remaining this month. Requested: 3h."
     * }
     */
    public function store(\App\Http\Requests\Api\CreateLabBookingRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', LabBooking::class);
            $validated = $request->validated();

            $booking = $this->bookingService->createBooking(Auth::user(), $validated);

            $statusMessage = $booking->status === LabBooking::STATUS_APPROVED
                ? 'Booking created and auto-approved!'
                : 'Booking submitted for approval.';

            return response()->json([
                'success' => true,
                'message' => $statusMessage,
                'data' => $booking,
            ], 201);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            \Illuminate\Support\Facades\Log::warning('Unauthorized booking creation', ['user_id' => Auth::id()]);
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to create booking', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get booking details.
     *
     * @urlParam id integer required The booking ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "title": "PCR Experiment",
     *     "purpose": "DNA amplification for research",
     *     "starts_at": "2024-01-15T09:00:00Z",
     *     "ends_at": "2024-01-15T12:00:00Z",
     *     "duration_hours": 3,
     *     "status": "approved",
     *     "lab_space": {...}
     *   }
     * }
     */
    public function show(int $id): JsonResponse
    {
        try {
            $booking = LabBooking::with(['labSpace', 'user:id,username'])->findOrFail($id);

            $this->authorize('view', $booking);

            $booking->append(['duration_hours', 'is_cancellable', 'can_check_in', 'can_check_out', 'status_color']);

            return response()->json([
                'success' => true,
                'data' => $booking,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            \Illuminate\Support\Facades\Log::warning('Unauthorized booking access', ['user_id' => Auth::id(), 'booking_id' => $id]);
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to retrieve booking', ['error' => $e->getMessage(), 'user_id' => Auth::id(), 'booking_id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve booking'], 500);
        }
    }

    /**
     * Cancel a booking.
     *
     * Users can cancel their own pending or approved future bookings.
     * Quota is refunded on cancellation.
     *
     * @urlParam id integer required The booking ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Booking cancelled successfully",
     *   "data": {...}
     * }
     * @response 403 {"success": false, "message": "This action is unauthorized."}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $booking = LabBooking::findOrFail($id);

            $this->authorize('delete', $booking);

            $booking = $this->bookingService->cancelBooking($booking);

            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled successfully. Quota refunded.',
                'data' => $booking,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            \Illuminate\Support\Facades\Log::warning('Unauthorized booking cancellation', ['user_id' => Auth::id(), 'booking_id' => $id]);
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to cancel booking', ['error' => $e->getMessage(), 'user_id' => Auth::id(), 'booking_id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to cancel booking'], 500);
        }
    }
}
