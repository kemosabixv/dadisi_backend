<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabBooking;
use App\Services\Contracts\LabBookingServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * @group Lab Bookings
 *
 * Endpoints for managing user lab space bookings.
 *
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
     * @queryParam group string Filter by status group (active, history). Example: active
     * @queryParam status string Filter by status (pending, confirmed, rejected, cancelled, completed, no_show). Example: confirmed
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
     *       "status": "confirmed",
     *       "lab_space": {"id": 1, "name": "Nairobi Central Wet Lab"}
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = LabBooking::forUser($user->id)->with(['labSpace', 'user.memberProfile']);

            // Group filtering
            if ($request->has('group')) {
                if ($request->group === 'active') {
                    $query->whereIn('status', [LabBooking::STATUS_PENDING, LabBooking::STATUS_CONFIRMED]);
                } elseif ($request->group === 'history') {
                    $query->whereIn('status', [
                        LabBooking::STATUS_CANCELLED,
                        LabBooking::STATUS_COMPLETED,
                        LabBooking::STATUS_REJECTED,
                        LabBooking::STATUS_NO_SHOW,
                    ]);
                }
            }

            // Specific status filter
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Only upcoming
            if ($request->boolean('upcoming')) {
                $query->upcoming();
            }

            $bookings = $query->orderBy('starts_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => \App\Http\Resources\LabBookingResource::collection($bookings),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to retrieve bookings', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);

            return response()->json(['success' => false, 'message' => 'Failed to retrieve bookings'], 500);
        }
    }

    /**
     * Initiate a booking (Two-Phase: Phase 1 - Hold).
     *
     * Creates a temporary slot hold and returns a payment preview.
     */
    public function initiate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lab_space_id' => 'required|integer|exists:lab_spaces,id',
                'slots' => 'required|array|min:1',
                'slots.*.starts_at' => 'required|date|after:now',
                'slots.*.ends_at' => 'required|date|after:slots.*.starts_at',
                'purpose' => 'nullable|string|max:500',
                'title' => 'nullable|string|max:255',
                'guest_name' => 'nullable|string|max:255',
                'guest_email' => 'nullable|email|max:255',
            ]);

            $result = $this->bookingService->initiateBooking(Auth::user(), $validated);

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Renew a slot hold (Heartbeat).
     */
    public function renewHold(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['reference' => 'required|string']);
            $result = $this->bookingService->renewHold($validated['reference']);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Confirm a booking (Two-Phase: Phase 2 - Lock).
     */
    public function confirm(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reference' => 'required|string',
                'payment_id' => 'nullable|string',
                'payment_method' => 'required|string',
            ]);

            $result = $this->bookingService->confirmBooking(
                $validated['reference'],
                $validated['payment_id'],
                $validated['payment_method']
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Discover occurrences for recurring bookings with skip & append preview.
     */
    public function discoverRecurring(Request $request): JsonResponse
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'lab_space_id' => 'required|integer|exists:lab_spaces,id',
                'target_count' => 'required|integer|min:1|max:100',
                'days_of_week' => 'required|array',
                'days_of_week.*' => 'string|in:Mon,Tue,Wed,Thu,Fri,Sat,Sun',
                'start_time' => 'required|string|regex:/^\d{2}:\d{2}$/',
                'duration_minutes' => 'required|integer|min:15|max:1440',
                'start_date' => 'required|date|after_or_equal:today',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid parameters',
                    'errors' => $validator->errors()
                ], 200);
            }

            $validated = $validator->validated();

            $options = $this->bookingService->discoverRecurringSlots(
                $validated['lab_space_id'],
                $validated,
                Auth::user()
            );

            // Handle validation result within success response
            if (isset($options['success']) && $options['success'] === false) {
                return response()->json($options, 200);
            }

            return response()->json([
                'success' => true,
                'data' => $options,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * Discover optimal slots for flexible bookings.
     */
    public function discoverFlexible(Request $request): JsonResponse
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'lab_space_id' => 'required|integer|exists:lab_spaces,id',
                'target_hours' => 'required|numeric|min:0.5',
                'preferred_days' => 'nullable|array',
                'preferred_days.*' => 'string|in:Mon,Tue,Wed,Thu,Fri,Sat,Sun',
                'start_date' => 'nullable|date|after_or_equal:today',
                'end_date' => 'nullable|date|after:start_date',
                'max_daily_hours' => 'nullable|numeric|min:0.5|max:24',
                'consecutive_days' => 'nullable|boolean',
                'preferred_start_time' => 'nullable|string|regex:/^\d{2}:\d{2}$/',
                'preferred_end_time' => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid parameters',
                    'errors' => $validator->errors()
                ], 200);
            }

            $validated = $validator->validated();

            $options = $this->bookingService->discoverFlexibleSlots(
                $validated['lab_space_id'],
                $validated,
                Auth::user()
            );

            // Handle validation result within success response
            if (isset($options['success']) && $options['success'] === false) {
                return response()->json($options, 200);
            }

            return response()->json([
                'success' => true,
                'data' => $options,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * Public Guest Cancellation via token.
     */
    public function guestCancel(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer|exists:lab_bookings,id',
                'token' => 'required|string',
                'reason' => 'nullable|string|max:500',
            ]);

            $booking = LabBooking::findOrFail($validated['id']);
            $expectedToken = hash_hmac('sha256', $booking->id.$booking->created_at, config('app.key'));

            if (! hash_equals($expectedToken, $validated['token'])) {
                return response()->json(['success' => false, 'message' => 'Invalid cancellation token.'], 403);
            }

            $result = $this->bookingService->cancelBooking($booking, $validated['reason'] ?? 'Guest cancelled via link');

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Create a new booking (Legacy/Direct).
     */
    public function store(\App\Http\Requests\Api\CreateLabBookingRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', LabBooking::class);

            $dto = \App\DTOs\CreateLabBookingDTO::fromArray($request->validated());
            $validated = $dto->toArray();

            $result = $this->bookingService->createBooking(Auth::user(), $validated);

            if (! $result['success']) {
                $status = isset($result['is_race_condition']) && $result['is_race_condition'] ? 409 : 422;

                return response()->json($result, $status);
            }

            $response = [
                'success' => true,
                'message' => $result['message'],
                'data' => new \App\Http\Resources\LabBookingResource($result['booking']),
                'payment_required' => $result['payment_required'] ?? false,
                'total_price' => $result['total_price'] ?? 0,
            ];

            if (isset($result['redirect_url'])) {
                $response['redirect_url'] = $result['redirect_url'];
                $response['transaction_id'] = $result['transaction_id'];
            }

            return response()->json($response, 201);
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
     *     "status": "confirmed",
     *     "payment_method": "quota",
     *     "attended_slots": 1,
     *     "no_show_slots": 0,
     *     "future_slots": 0,
     *     "lab_space": {...},
     *     "audit_logs": [...]
     *   }
     * }
     */
    public function show(int $id): JsonResponse
    {
        try {
            $booking = LabBooking::with(['labSpace', 'user:id,username', 'bookingSeries', 'auditLogs.user:id,username'])->findOrFail($id);

            $this->authorize('view', $booking);

            $booking->append([
                'duration_hours',
                'is_cancellable',
                'can_check_in',
                'can_check_out',
                'status_color',
                'is_deadline_reached',
            ]);

            // Get slot-level status breakdown
            $slotBreakdown = $this->getSlotStatusBreakdown($booking);

            return response()->json([
                'success' => true,
                'data' => array_merge(
                    (new \App\Http\Resources\LabBookingResource($booking))->toArray(request()),
                    [
                        'payment_method' => $booking->payment?->method ?? $booking->payment_method,
                        'attended_slots' => $slotBreakdown['attended'],
                        'no_show_slots' => $slotBreakdown['no_show'],
                        'future_slots' => $slotBreakdown['future'],
                    ]
                ),
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
     * Get slot-level status breakdown for a booking or booking series.
     *
     * For singular bookings, returns 0-1 for each status.
     * For booking series, returns count of bookings in each status.
     *
     * @return array{attended: int, no_show: int, future: int}
     */
    private function getSlotStatusBreakdown(LabBooking $booking): array
    {
        // For series bookings, get all bookings in the series
        $bookings = $booking->bookingSeries
            ? $booking->bookingSeries->bookings
            : collect([$booking]);

        $attended = $bookings->filter(function ($b) {
            return $b->checked_in_at !== null || $b->status === LabBooking::STATUS_COMPLETED;
        })->count();

        $no_show = $bookings->filter(function ($b) {
            return $b->status === LabBooking::STATUS_NO_SHOW;
        })->count();

        $future = $bookings->filter(function ($b) {
            return $b->starts_at->isFuture()
                && ! in_array($b->status, [LabBooking::STATUS_COMPLETED, LabBooking::STATUS_NO_SHOW, LabBooking::STATUS_CANCELLED])
                && $b->checked_in_at === null;
        })->count();

        return [
            'attended' => $attended,
            'no_show' => $no_show,
            'future' => $future,
        ];
    }

    /**
     * Public Guest Show via token.
     */
    public function publicShow(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer|exists:lab_bookings,id',
                'token' => 'required|string',
            ]);

            $booking = LabBooking::with('labSpace:id,name')->findOrFail($request->id);
            $expectedToken = hash_hmac('sha256', $booking->id.$booking->created_at, config('app.key'));

            if (! hash_equals($expectedToken, $request->token)) {
                return response()->json(['success' => false, 'message' => 'Invalid or expired link.'], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $booking->id,
                    'space_name' => $booking->labSpace->name,
                    'starts_at' => $booking->starts_at,
                    'ends_at' => $booking->ends_at,
                    'status' => $booking->status,
                    'is_cancellable' => $booking->is_cancellable,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to retrieve booking details.'], 422);
        }
    }

    /**
     * Get refund preview for a booking.
     */
    public function refundPreview(Request $request): JsonResponse
    {
        try {
            $booking = null;

            // Handle both authenticated and guest (via token)
            if ($request->has('token')) {
                $validated = $request->validate([
                    'id' => 'required|integer|exists:lab_bookings,id',
                    'token' => 'required|string',
                ]);
                $booking = LabBooking::findOrFail($request->id);
                $expectedToken = hash_hmac('sha256', $booking->id.$booking->created_at, config('app.key'));
                if (! hash_equals($expectedToken, $request->token)) {
                    return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
                }
            } else {
                $request->validate(['id' => 'required|integer|exists:lab_bookings,id']);
                $booking = LabBooking::findOrFail($request->id);
                $this->authorize('view', $booking);
            }

            $preview = $this->bookingService->refundPreview($booking);

            return response()->json([
                'success' => true,
                'data' => $preview,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Create a guest lab booking (no auth required).
     *
     * @bodyParam lab_space_id integer required The ID of the lab space. Example: 1
     * @bodyParam starts_at string required Start time in ISO 8601 format. Example: 2024-01-15T09:00:00Z
     * @bodyParam ends_at string required End time in ISO 8601 format. Example: 2024-01-15T12:00:00Z
     * @bodyParam purpose string required Description. Example: DNA amplification experiment
     * @bodyParam guest_name string required Guest's full name. Example: John Doe
     * @bodyParam guest_email string required Guest's email for notifications. Example: john@example.com
     * @bodyParam title string Optional title. Example: PCR Experiment
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Booking created. Complete payment to confirm.",
     *   "redirect_url": "https://pay.pesapal.com/..."
     * }
     *
     * @unauthenticated
     */
    public function guestStore(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lab_space_id' => 'required|integer|exists:lab_spaces,id',
                'starts_at' => 'required|date|after:now',
                'ends_at' => 'required|date|after:starts_at',
                'purpose' => 'nullable|string|max:500',
                'title' => 'nullable|string|max:255',
                'guest_name' => 'required|string|max:255',
                'guest_email' => 'required|email|max:255',
                'slot_type' => ['nullable', 'string', Rule::in(['hourly', 'half_day', 'full_day'])],
            ]);

            $result = $this->bookingService->createGuestBooking($validated);

            if (! $result['success']) {
                $status = isset($result['is_race_condition']) && $result['is_race_condition'] ? 409 : 422;

                return response()->json($result, $status);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['booking'],
                'payment_required' => true,
                'total_price' => $result['total_price'],
                'redirect_url' => $result['redirect_url'],
                'transaction_id' => $result['transaction_id'],
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to create guest booking', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel a booking (POST endpoint).
     *
     * Users can cancel their own future bookings.
     * Returns refund preview information with the cancellation response.
     * Quota is refunded on cancellation.
     *
     * @urlParam id integer required The booking ID. Example: 1
     *
     * @bodyParam cancellation_reason string required Reason for cancellation. Example: Schedule conflict
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Booking cancelled successfully",
     *   "data": {...booking data...},
     *   "refund": {
     *     "refundable": true,
     *     "amount": 2500,
     *     "reason": "Full refund - cancelled within allowed window"
     *   }
     * }
     * @response 403 {"success": false, "message": "Unauthorized"}
     * @response 404 {"success": false, "message": "Booking not found"}
     */
    public function cancel(int $id, Request $request): JsonResponse
    {
        try {
            $booking = LabBooking::findOrFail($id);

            $this->authorize('delete', $booking);

            $validated = $request->validate([
                'cancellation_reason' => 'required|string|max:255',
            ]);

            // Get refund preview BEFORE cancelling
            try {
                $refundPreview = $this->bookingService->refundPreview($booking);
            } catch (\Exception $e) {
                $refundPreview = [
                    'refundable' => false,
                    'amount' => 0,
                    'reason' => 'Unable to calculate refund',
                ];
            }

            // Cancel the booking
            $result = $this->bookingService->cancelBooking($booking, $validated['cancellation_reason']);

            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled successfully',
                'data' => $result['booking']->load(['labSpace']),
                'refund' => $refundPreview,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            \Illuminate\Support\Facades\Log::warning('Unauthorized booking cancellation', ['user_id' => Auth::id(), 'booking_id' => $id]);

            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to cancel booking', ['error' => $e->getMessage(), 'user_id' => Auth::id(), 'booking_id' => $id]);

            return response()->json(['success' => false, 'message' => 'Failed to cancel booking'], 500);
        }
    }

    /**
     * Cancel a booking (DELETE endpoint - legacy support).
     *
     * Users can cancel their own future bookings.
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

            $result = $this->bookingService->cancelBooking($booking);

            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled successfully',
                'data' => $result['booking']->load(['labSpace']),
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

    /**
     * Check in to a booking.
     *
     * Users can self check-in to their bookings within the session time.
     *
     * @urlParam id integer required The booking ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Checked in successfully",
     *   "data": {...}
     * }
     */
    public function checkIn(int $id): JsonResponse
    {
        try {
            $booking = LabBooking::findOrFail($id);

            $this->authorize('checkIn', $booking);

            $booking = $this->bookingService->checkIn($booking);

            return response()->json([
                'success' => true,
                'message' => 'Checked in successfully',
                'data' => $booking,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage() ?: 'Unauthorized'], 403);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to check in', ['error' => $e->getMessage(), 'booking_id' => $id]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Check in to a booking via Lab Space checkin_token.
     *
     * @bodyParam token string required The Lab Space checkin_token. Example: LAB-ABCDEF123456
     */
    public function checkinByToken(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string',
            ]);

            $booking = $this->bookingService->checkInByToken(Auth::user(), $validated['token']);

            return response()->json([
                'success' => true,
                'message' => 'Checked in successfully',
                'data' => $booking,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Invalid QR Code'], 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to check in via token', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);
        }
    }

    /**
     * Resolve a booking conflict manually.
     *
     * @bodyParam lab_space_id integer optional The target Lab Space ID.
     * @bodyParam starts_at string required The new start time. Example: 2025-02-15T09:00:00Z
     * @bodyParam ends_at string required The new end time. Example: 2025-02-15T12:00:00Z
     */
    public function resolveConflict(Request $request, int $id): JsonResponse
    {
        try {
            $booking = LabBooking::findOrFail($id);

            // Authorization (handled in service, but check user_id here for 403)
            if ($booking->user_id !== Auth::id()) {
                throw new \Illuminate\Auth\Access\AuthorizationException('You are not authorized to resolve this conflict.');
            }

            if ($booking->status !== LabBooking::STATUS_PENDING_USER_RESOLUTION) {
                return response()->json(['success' => false, 'message' => 'This booking does not require conflict resolution.'], 422);
            }

            $validated = $request->validate([
                'lab_space_id' => 'nullable|integer|exists:lab_spaces,id',
                'starts_at' => 'required|date|after:now',
                'ends_at' => 'required|date|after:starts_at',
            ]);

            $result = $this->bookingService->resolveConflict($booking, $validated);

            if (! $result['success']) {
                return response()->json($result, 422);
            }

            return response()->json($result);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to resolve booking conflict', ['error' => $e->getMessage(), 'booking_id' => $id]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
