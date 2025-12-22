<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabBooking;
use App\Services\LabBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin - Lab Bookings
 *
 * Admin endpoints for managing lab bookings, approvals, and attendance.
 * @authenticated
 */
class AdminLabBookingController extends Controller
{
    public function __construct(
        protected LabBookingService $bookingService
    ) {}

    /**
     * List all lab bookings (paginated).
     *
     * @queryParam status string Filter by status. Example: pending
     * @queryParam lab_space_id integer Filter by lab space. Example: 1
     * @queryParam user_id integer Filter by user. Example: 5
     * @queryParam date_from string Filter bookings from this date. Example: 2024-01-01
     * @queryParam date_to string Filter bookings to this date. Example: 2024-01-31
     * @queryParam per_page integer Items per page. Default: 15. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": [...],
     *   "meta": {...}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAll', LabBooking::class);

        $query = LabBooking::with(['labSpace', 'user:id,username,email']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('lab_space_id')) {
            $query->where('lab_space_id', $request->lab_space_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('date_from')) {
            $query->where('starts_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('starts_at', '<=', $request->date_to);
        }

        $perPage = $request->input('per_page', 15);
        $bookings = $query->orderBy('starts_at', 'desc')->paginate($perPage);

        // Add computed attributes
        $bookings->getCollection()->each(function ($booking) {
            $booking->append([
                'duration_hours',
                'is_past_grace_period',
                'can_check_in',
                'can_check_out',
                'status_color',
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $bookings->items(),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    /**
     * Get booking details.
     *
     * @urlParam id integer required The booking ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {...}
     * }
     */
    public function show(int $id): JsonResponse
    {
        $booking = LabBooking::with(['labSpace', 'user:id,username,email'])->findOrFail($id);

        $this->authorize('view', $booking);

        $booking->append([
            'duration_hours',
            'is_past_grace_period',
            'can_check_in',
            'can_check_out',
            'is_cancellable',
            'status_color',
        ]);

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }

    /**
     * Approve a pending booking.
     *
     * @urlParam id integer required The booking ID. Example: 1
     * @bodyParam admin_notes string Optional notes for the user. Example: Approved. Please ensure lab safety gear.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Booking approved successfully",
     *   "data": {...}
     * }
     * @response 422 {"success": false, "message": "Only pending bookings can be approved"}
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $booking = LabBooking::findOrFail($id);

        $this->authorize('approve', $booking);

        if ($booking->status !== LabBooking::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending bookings can be approved',
            ], 422);
        }

        $booking = $this->bookingService->approveBooking(
            $booking,
            $request->input('admin_notes')
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking approved successfully',
            'data' => $booking->load(['labSpace', 'user:id,username']),
        ]);
    }

    /**
     * Reject a pending booking.
     *
     * @urlParam id integer required The booking ID. Example: 1
     * @bodyParam rejection_reason string required Reason for rejection. Example: Lab is fully booked for maintenance.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Booking rejected. Quota refunded to user.",
     *   "data": {...}
     * }
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $booking = LabBooking::findOrFail($id);

        $this->authorize('reject', $booking);

        if ($booking->status !== LabBooking::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending bookings can be rejected',
            ], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $booking = $this->bookingService->rejectBooking(
            $booking,
            $validated['rejection_reason']
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking rejected. Quota refunded to user.',
            'data' => $booking->load(['labSpace', 'user:id,username']),
        ]);
    }

    /**
     * Check in a booking.
     *
     * Records the user's arrival at the lab.
     *
     * @urlParam id integer required The booking ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "User checked in successfully",
     *   "data": {...}
     * }
     */
    public function checkIn(int $id): JsonResponse
    {
        $booking = LabBooking::findOrFail($id);

        $this->authorize('checkIn', $booking);

        $booking = $this->bookingService->checkIn($booking);

        return response()->json([
            'success' => true,
            'message' => 'User checked in successfully',
            'data' => $booking->load(['labSpace', 'user:id,username']),
        ]);
    }

    /**
     * Check out a booking.
     *
     * Records the user's departure and calculates actual duration.
     *
     * @urlParam id integer required The booking ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "User checked out. Booking completed.",
     *   "data": {...}
     * }
     */
    public function checkOut(int $id): JsonResponse
    {
        $booking = LabBooking::findOrFail($id);

        $this->authorize('checkOut', $booking);

        $booking = $this->bookingService->checkOut($booking);

        return response()->json([
            'success' => true,
            'message' => 'User checked out. Booking completed.',
            'data' => $booking->load(['labSpace', 'user:id,username']),
        ]);
    }

    /**
     * Mark a booking as no-show.
     *
     * Manually marks a booking as no-show after the 30-minute grace period.
     * Quota is NOT refunded for no-shows.
     *
     * @urlParam id integer required The booking ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Booking marked as no-show",
     *   "data": {...}
     * }
     */
    public function markNoShow(int $id): JsonResponse
    {
        $booking = LabBooking::findOrFail($id);

        $this->authorize('markNoShow', $booking);

        $booking = $this->bookingService->markNoShow($booking);

        return response()->json([
            'success' => true,
            'message' => 'Booking marked as no-show. Quota consumed as penalty.',
            'data' => $booking->load(['labSpace', 'user:id,username']),
        ]);
    }
}
