<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Contracts\EventAttendanceServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Admin Event Attendance
 * @groupDescription Endpoints for managing event check-ins and tracking attendee statistics.
 */
class AdminEventAttendanceController extends Controller
{
    public function __construct(
        private EventAttendanceServiceContract $attendanceService
    ) {
        $this->middleware(['auth:sanctum', 'admin']);
    }

    /**
     * Get Attendance Stats
     * 
     * Returns aggregate attendance data for a specific event.
     * 
     * @group Admin Event Attendance
     * @authenticated
     * @urlParam event required The event ID. Example: 1
     * 
     * @response 200 {
     *   "total_registered": 100,
     *   "attended": 45,
     *   "remaining": 55,
     *   "percentage": 45.0,
     *   "breakdown": {...}
     * }
     */
    public function stats(Event $event): JsonResponse
    {
        try {
            $this->authorize('update', $event);
            $stats = $this->attendanceService->getAttendanceStats($event);
            return response()->json($stats);
        } catch (\Exception $e) {
            Log::error('Failed to get attendance stats', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve statistics'], 500);
        }
    }

    /**
     * Verify/Check-in QR Token
     * 
     * Validates a ticket QR token and marks the attendee as checked in.
     * Supports both free RSVPs and paid tickets.
     * 
     * @group Admin Event Attendance
     * @authenticated
     * @urlParam event required The event ID. Example: 1
     * @bodyParam token string required The QR code token. Example: TKT-abc123xyz
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "RSVP Check-in successful!",
     *   "attendee": {"name": "John Doe", "type": "RSVP", "time": "2025-12-03T14:30:00Z"}
     * }
     * @response 409 {
     *   "success": false,
     *   "message": "User already checked in.",
     *   "attendee": {...}
     * }
     */
    public function scan(Request $request, Event $event): JsonResponse
    {
        try {
            $this->authorize('update', $event);

            $validated = $request->validate([
                'token' => 'required|string',
            ]);

            $result = $this->attendanceService->scanTicket($event, $validated['token']);

            if (!$result['success']) {
                $statusCode = $result['status_code'] ?? 400;
                unset($result['status_code']);
                return response()->json($result, $statusCode);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to scan ticket', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['success' => false, 'message' => 'Failed to process check-in'], 500);
        }
    }

    /**
     * Get Attendee List
     * 
     * Returns a consolidated list of all confirmed and attended guests (both RSVP and Paid).
     * 
     * @group Admin Event Attendance
     * @authenticated
     * @urlParam event required The event ID. Example: 1
     * 
     * @response 200 [
     *   {
     *     "id": "reg_1",
     *     "name": "John Doe",
     *     "email": "john@example.com",
     *     "type": "RSVP",
     *     "status": "attended",
     *     "checked_in_at": "2025-12-03T14:30:00Z"
     *   }
     * ]
     */
    public function attendees(Request $request, Event $event): JsonResponse
    {
        try {
            $this->authorize('update', $event);

            $attendees = $this->attendanceService->listAttendees($event);

            return response()->json($attendees);
        } catch (\Exception $e) {
            Log::error('Failed to list attendees', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve attendee list'], 500);
        }
    }
}
