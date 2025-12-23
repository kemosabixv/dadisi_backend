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
 * APIs for administrative moderation of lab space bookings.
 * Includes approving/rejecting requests and managing usage statistics.
 */
class AdminLabBookingController extends Controller
{
    public function __construct(
        protected LabBookingService $bookingService
    ) {}

    /**
     * List all lab bookings (Admin)
     *
     * Retrieves a paginated list of all lab bookings across all users and spaces.
     *
     * @authenticated
     * @queryParam status string Filter by status (pending, approved, rejected). Example: pending
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

    /**
     * Get lab booking statistics.
     *
     * Returns aggregate statistics for lab usage and attendance.
     *
     * @queryParam period string Filter period (week, month, quarter, year). Default: month. Example: month
     * @queryParam lab_space_id integer Filter by specific lab space. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "total_bookings": 150,
     *     "by_status": {
     *       "approved": 100,
     *       "pending": 10,
     *       "rejected": 15,
     *       "completed": 80,
     *       "no_show": 5,
     *       "cancelled": 20
     *     },
     *     "hours": {
     *       "total_booked": 400,
     *       "total_used": 320,
     *       "average_per_booking": 4.0
     *     },
     *     "attendance": {
     *       "show_rate": 94.1,
     *       "no_show_count": 5,
     *       "completed_count": 80
     *     },
     *     "top_spaces": [...],
     *     "top_users": [...]
     *   }
     * }
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAll', LabBooking::class);

        // Determine date range
        $period = $request->input('period', 'month');
        $startDate = match ($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };

        $query = LabBooking::where('created_at', '>=', $startDate);

        if ($request->has('lab_space_id')) {
            $query->where('lab_space_id', $request->lab_space_id);
        }

        // Get counts by status
        $byStatus = $query->clone()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalBookings = array_sum($byStatus);

        // Get hours statistics
        $hoursStats = $query->clone()
            ->selectRaw('
                SUM(TIMESTAMPDIFF(HOUR, starts_at, ends_at)) as total_booked,
                SUM(COALESCE(actual_duration_hours, 0)) as total_used,
                COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_count
            ')
            ->first();

        $totalBooked = (int) ($hoursStats->total_booked ?? 0);
        $totalUsed = (float) ($hoursStats->total_used ?? 0);
        $completedCount = (int) ($hoursStats->completed_count ?? 0);

        // Calculate attendance rate
        $approvedAndCompleted = ($byStatus['approved'] ?? 0) + ($byStatus['completed'] ?? 0);
        $noShowCount = $byStatus['no_show'] ?? 0;
        $showRate = $approvedAndCompleted > 0
            ? round((($approvedAndCompleted - $noShowCount) / $approvedAndCompleted) * 100, 1)
            : 0;

        // Top spaces by usage
        $topSpaces = $query->clone()
            ->with('labSpace:id,name,slug')
            ->selectRaw('lab_space_id, COUNT(*) as booking_count')
            ->groupBy('lab_space_id')
            ->orderByDesc('booking_count')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'space' => $item->labSpace?->name ?? 'Unknown',
                'slug' => $item->labSpace?->slug,
                'bookings' => $item->booking_count,
            ]);

        // Top users by bookings
        $topUsers = $query->clone()
            ->with('user:id,username,email')
            ->selectRaw('user_id, COUNT(*) as booking_count')
            ->groupBy('user_id')
            ->orderByDesc('booking_count')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'user' => $item->user?->username ?? 'Unknown',
                'email' => $item->user?->email,
                'bookings' => $item->booking_count,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'date_range' => [
                    'from' => $startDate->toDateString(),
                    'to' => now()->toDateString(),
                ],
                'total_bookings' => $totalBookings,
                'by_status' => [
                    'pending' => $byStatus['pending'] ?? 0,
                    'approved' => $byStatus['approved'] ?? 0,
                    'rejected' => $byStatus['rejected'] ?? 0,
                    'completed' => $byStatus['completed'] ?? 0,
                    'no_show' => $byStatus['no_show'] ?? 0,
                    'cancelled' => $byStatus['cancelled'] ?? 0,
                ],
                'hours' => [
                    'total_booked' => $totalBooked,
                    'total_used' => $totalUsed,
                    'average_per_booking' => $completedCount > 0 ? round($totalUsed / $completedCount, 1) : 0,
                ],
                'attendance' => [
                    'show_rate' => $showRate,
                    'no_show_count' => $noShowCount,
                    'completed_count' => $completedCount,
                ],
                'top_spaces' => $topSpaces,
                'top_users' => $topUsers,
            ],
        ]);
    }
}

