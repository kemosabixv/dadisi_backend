<?php

namespace App\Http\Controllers\Api\Admin;

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
        protected LabBookingService $bookingService,
        protected \App\Services\RefundService $refundService
    ) {}

    /**
     * List all lab bookings (Admin)
     *
     * Retrieves a paginated list of all lab bookings across all users and spaces.
     * Supervisors can only see bookings for their assigned spaces.
     *
     * @authenticated
     * @queryParam status string Filter by status (pending, confirmed, rejected). Example: confirmed
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

        // Scope to assigned spaces for supervisors
        $user = $request->user();
        if ($user->hasRole('lab_supervisor')) {
            $assignedIds = $user->assignedLabSpaces()->pluck('lab_spaces.id')->toArray();
            $query->whereIn('lab_space_id', $assignedIds);
        }

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
            'purpose',
            'admin_notes',
            'reference',
        ]);

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }

    /**
     * Check in a guest booking.
     *
     * Records arrival for guest bookings which don't have a user account.
     *
     * @urlParam id integer required The booking ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Guest checked in successfully",
     *   "data": {...}
     * }
     */
    public function checkInGuest(Request $request, int $id): JsonResponse
    {
        $booking = LabBooking::findOrFail($id);

        $this->authorize('checkIn', $booking);

        $checkInAt = $request->has('check_in_at') 
            ? \Carbon\Carbon::parse($request->check_in_at) 
            : null;

        $booking = $this->bookingService->checkIn($booking, $checkInAt, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Guest checked in successfully',
            'data' => $booking->load(['labSpace', 'user:id,username']),
        ]);
    }

    /**
     * Check in a booking.
     *
     * @urlParam id integer required The booking ID.
     */
    public function checkIn(Request $request, int $id): JsonResponse
    {
        $booking = LabBooking::findOrFail($id);
        $this->authorize('checkIn', $booking);

        $checkInAt = $request->has('check_in_at') 
            ? \Carbon\Carbon::parse($request->check_in_at) 
            : null;

        $booking = $this->bookingService->checkIn($booking, $checkInAt, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Booking checked in successfully',
            'data' => $booking->load(['labSpace', 'user:id,username']),
        ]);
    }

    /**
     * Undo check-in for a booking.
     *
     * @urlParam id integer required The booking ID.
     */
    public function undoCheckIn(int $id): JsonResponse
    {
        $booking = LabBooking::findOrFail($id);
        $this->authorize('update', $booking);

        $booking = $this->bookingService->undoCheckIn($booking);

        return response()->json([
            'success' => true,
            'message' => 'Check-in undone successfully',
            'data' => $booking->load(['labSpace', 'user:id,username']),
        ]);
    }


    /**
     * Mark a booking as no-show.
     *
     * @urlParam id integer required The booking ID.
     */
    public function markNoShow(int $id): JsonResponse
    {
        $booking = LabBooking::findOrFail($id);
        $this->authorize('update', $booking);

        $booking = $this->bookingService->markNoShow($booking);

        return response()->json([
            'success' => true,
            'message' => 'Booking marked as no-show successfully',
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
     *       "confirmed": 100,
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
                SUM(CASE WHEN status = "completed" THEN TIMESTAMPDIFF(HOUR, starts_at, ends_at) ELSE 0 END) as total_used,
                COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_count
            ')
            ->first();

        $totalBooked = (int) ($hoursStats->total_booked ?? 0);
        $totalUsed = (float) ($hoursStats->total_used ?? 0);
        $completedCount = (int) ($hoursStats->completed_count ?? 0);

        // Calculate attendance rate
        $confirmedAndCompleted = ($byStatus['confirmed'] ?? 0) + ($byStatus['completed'] ?? 0);
        $noShowCount = $byStatus['no_show'] ?? 0;
        $showRate = $confirmedAndCompleted > 0
            ? round((($confirmedAndCompleted - $noShowCount) / $confirmedAndCompleted) * 100, 1)
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
                    'confirmed' => $byStatus['confirmed'] ?? 0,
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

    /**
     * Cancel a lab booking (staff action).
     *
     * Cancels a booking and optionally initiates refund processing.
     * Logs the cancellation reason to audit trail.
     *
     * @urlParam id integer required The booking ID. Example: 1
     * @bodyParam reason string optional Reason for cancellation. Example: Requested by member
     * @bodyParam initiate_refund boolean optional Whether to initiate refund request. Default: false. Example: true
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Booking cancelled successfully",
     *   "data": {
     *     "id": 1,
     *     "status": "cancelled",
     *     "refund_preview": {
     *       "original_amount": 500,
     *       "refund_amount": 425,
     *       "deduction": 75,
     *       "reason": "Late cancellation (within 24 hours)"
     *     }
     *   }
     * }
     */
    public function cancelBooking(Request $request, int $id): JsonResponse
    {
        $booking = LabBooking::findOrFail($id);
        $this->authorize('cancelBooking', $booking);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'initiate_refund' => 'boolean',
        ]);

        // Check if booking is already cancelled
        if ($booking->status === LabBooking::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'Booking is already cancelled',
            ], 422);
        }

        $oldStatus = $booking->status;

        // Update booking status to cancelled and release quota if needed
        if ($booking->quota_consumed) {
            $this->bookingService->releaseBookingQuota($booking);
            $booking->update([
                'status' => LabBooking::STATUS_CANCELLED,
                'quota_consumed' => false
            ]);
        } else {
            $booking->update(['status' => LabBooking::STATUS_CANCELLED]);
        }

        // Log cancellation to audit trail
        \App\Models\BookingAuditLog::create([
            'lab_booking_id' => $booking->id,
            'user_id' => $request->user()->id,
            'action' => 'cancelled_by_staff',
            'old_status' => $oldStatus,
            'new_status' => LabBooking::STATUS_CANCELLED,
            'notes' => $validated['reason'],
            'admin_notes' => $validated['reason'],
        ]);

        $refundPreview = $this->bookingService->calculateRefund($booking);

        $refundData = null;

        // If requested, initiate refund request via RefundService
        if ($validated['initiate_refund'] ?? false) {
            try {
                $refund = $this->refundService->submitRefundRequest(
                    'LabBooking',
                    $booking->id,
                    $validated['reason'],
                    'Staff initiated cancellation'
                );
                
                $refundData = [
                    'id' => $refund->id,
                    'amount' => $refund->amount,
                    'status' => $refund->status,
                ];
            } catch (\Exception $e) {
                // If refund fails, we still proceed with cancellation but notify about refund failure
                \Illuminate\Support\Facades\Log::error('Staff cancellation refund trigger failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Notify user/guest
        $notification = new \App\Notifications\LabBookingCancelledByStaff($booking, $validated['reason']);
        
        if ($booking->user) {
            $booking->user->notify($notification);
        } elseif ($booking->guest_email) {
            \Illuminate\Support\Facades\Notification::route('mail', $booking->guest_email)->notify($notification);
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled successfully' . ($refundData ? ' and refund request initiated' : ''),
            'data' => [
                'id' => $booking->id,
                'status' => $booking->status,
                'refund_info' => $refundData,
                'refund_preview' => $refundPreview,
            ],
        ], 200);
    }

    /**
     * Initiate a refund request for a canceled booking.
     *
     * Staff (Lab Manager/Admin/Lab Supervisor) can initiate refund requests.
     * Lab Supervisors must have their requests approved by Lab Manager/Admin.
     *
     * @urlParam id integer required The booking ID. Example: 1
     * @bodyParam amount_cents integer optional Refund amount in cents (overrides calculated). Example: 42500
     * @bodyParam notes string optional Notes for refund request. Example: Member requested early refund
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Refund request initiated successfully",
     *   "data": {
     *     "id": 1,
     *     "booking_id": 1,
     *     "status": "pending_approval",
     *     "requested_amount_cents": 42500,
     *     "calculated_amount_cents": 42500,
     *     "requested_by": {"id": 1, "name": "Staff Name", "role": "lab_supervisor"},
     *     "created_at": "2026-03-05"
     *   }
     * }
     */
    public function initiateRefund(Request $request, int $id): JsonResponse
    {
        $booking = LabBooking::findOrFail($id);
        $this->authorize('initiateRefund', $booking);

        $validated = $request->validate([
            'amount_cents' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        // Check if booking is eligible for refund (must be cancelled or completed with no-shows)
        if (!in_array($booking->status, [LabBooking::STATUS_CANCELLED, LabBooking::STATUS_COMPLETED])) {
            return response()->json([
                'success' => false,
                'message' => 'Only cancelled or completed bookings can be refunded',
            ], 422);
        }

        // Calculate refund if not explicitly provided
    $calculatedRefund = $this->bookingService->calculateRefund($booking);
    
    // Check if booking has a payment
    $payment = $booking->payment()->where('status', 'paid')->first() ?? $booking->payment;
    if (!$payment) {
        return response()->json([
            'success' => false,
            'message' => 'Cannot initiate refund: No payment found for this booking',
        ], 422);
    }

    $refundAmount = $validated['amount_cents'] ?? ($calculatedRefund['refund_amount'] * 100);

    // Create refund request record
    $refundRequest = \App\Models\Refund::create([
        'refundable_type' => 'lab_booking',
        'refundable_id' => $booking->id,
        'payment_id' => $payment->id,
        'amount' => (float) ($refundAmount / 100),
        'original_amount' => (float) $booking->total_price,
        'currency' => $payment->currency ?? 'KES',
        'status' => $request->user()->hasRole('lab_supervisor') ? 'pending_approval' : \App\Models\Refund::STATUS_APPROVED,
        'reason' => \App\Models\Refund::REASON_CANCELLATION,
        'customer_notes' => $validated['notes'] ?? null,
        'requested_at' => now(),
    ]);

    // Log refund request
    \App\Models\AuditLog::create([
        'user_id' => $request->user()->id,
        'action' => 'refund_requested',
        'model_type' => LabBooking::class,
        'model_id' => $booking->id,
        'changes' => [
            'refund_request_id' => $refundRequest->id,
            'amount_cents' => (int) $refundAmount,
            'status' => $refundRequest->status,
        ],
    ]);

    // Notify approvers if pending
    if ($refundRequest->status === 'pending_approval') {
        // TODO: Notify Lab Manager/Admin of pending refund approval
    }

    return response()->json([
        'success' => true,
        'message' => 'Refund request initiated successfully',
        'data' => [
            'id' => $refundRequest->id,
            'booking_id' => $booking->id,
            'status' => $refundRequest->status,
            'requested_amount_cents' => (int) ($refundRequest->amount * 100),
            'calculated_amount_cents' => (int) ($refundRequest->original_amount * 100 * ($refundRequest->amount / ($refundRequest->original_amount ?: 1))), // This is a bit complex, but aims to match the intent
            'requested_by' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'role' => $request->user()->roles()->first()?->name,
            ],
            'created_at' => $refundRequest->created_at->toDateString(),
        ],
    ], 200);
}

    /**
     * Get bookings for a specific lab space.
     *
     * Lists all bookings for a given lab with optional filters.
     *
     * @urlParam lab_id integer required The lab space ID. Example: 1
     * @queryParam status string Filter by status. Example: confirmed
     * @queryParam date_from string Filter from date. Example: 2026-03-01
     * @queryParam date_to string Filter to date. Example: 2026-03-31
     * @queryParam per_page integer Items per page. Default: 20. Example: 20
     *
     * @response 200 {
     *   "success": true,
     *   "data": [...],
     *   "meta": {...}
     * }
     */
    public function labBookings(Request $request, int $labId): JsonResponse
    {
        $lab = \App\Models\LabSpace::findOrFail($labId);
        $this->authorize('view', $lab);

        $query = LabBooking::where('lab_space_id', $labId)
            ->with(['labSpace', 'user:id,username,email']);

        // Scope for supervisors
        if ($request->user()->hasRole('lab_supervisor')) {
            if (!$request->user()->assignedLabSpaces()->where('lab_spaces.id', $labId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view bookings for this lab',
                ], 403);
            }
        }

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->where('starts_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('starts_at', '<=', $request->date_to);
        }

        $perPage = $request->input('per_page', 20);
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
     * Get aggregate attendance analytics.
     */
    public function attendanceAnalytics(Request $request): JsonResponse
    {
        $this->authorize('viewAll', LabBooking::class);
        
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $period = $request->input('period');

        if ($period && !$startDate && !$endDate) {
            $endDate = now()->toDateString();
            $startDate = match($period) {
                'week' => now()->subDays(7)->toDateString(),
                'month' => now()->subMonth()->toDateString(),
                'quarter' => now()->subMonths(3)->toDateString(),
                'year' => now()->subYear()->toDateString(),
                default => now()->subMonth()->toDateString(),
            };
        }
        
        $startCarbon = $startDate ? \Carbon\Carbon::parse($startDate) : null;
        $endCarbon = $endDate ? \Carbon\Carbon::parse($endDate)->endOfDay() : null;

        $analytics = $this->bookingService->getAttendanceAnalytics($startCarbon, $endCarbon);
        
        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * List attendance logs with filters.
     */
    public function attendanceLogs(Request $request): JsonResponse
    {
        $this->authorize('viewAll', LabBooking::class);
        
        $query = \App\Models\AttendanceLog::with(['booking.labSpace', 'user:id,username,email', 'markedBy:id,username']);
        
        // RBAC: Supervisors only see logs for their assigned labs
        $user = $request->user();
        if ($user->hasRole('lab_supervisor')) {
            $assignedIds = $user->assignedLabSpaces()->pluck('lab_spaces.id')->toArray();
            $query->whereIn('lab_id', $assignedIds);
        }

        if ($request->has('lab_space_id')) {
            $query->where('lab_id', $request->lab_space_id);
        }

        if ($request->has('booking_id')) {
            $query->where('booking_id', $request->booking_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('start_date')) {
            $query->whereDate('slot_start_time', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('slot_start_time', '<=', $request->end_date);
        }

        $logs = $query->orderBy('slot_start_time', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Mark attendance for a specific slot.
     */
    public function markSlotAttendance(Request $request, int $id): JsonResponse
    {
        $booking = LabBooking::findOrFail($id);
        $this->authorize('update', $booking);
        
        $validated = $request->validate([
            'slot_start_time' => 'required|date',
            'status' => 'required|string|in:attended,no_show,pending',
            'notes' => 'nullable|string|max:500',
        ]);
        
        $log = $this->bookingService->markSlotAttendance(
            $booking,
            \Carbon\Carbon::parse($validated['slot_start_time']),
            $validated['status'],
            $request->user(),
            $validated['notes'] ?? null
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Slot attendance updated successfully',
            'data' => $log->load(['user:id,username', 'markedBy:id,username']),
        ]);
    }
}
