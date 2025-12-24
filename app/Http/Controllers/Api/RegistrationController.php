<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegistrationResource;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Ticket;
use App\Services\EventService;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class RegistrationController extends Controller
{
    protected $eventService;
    protected $qrCodeService;

    public function __construct(EventService $eventService, QrCodeService $qrCodeService)
    {
        $this->eventService = $eventService;
        $this->qrCodeService = $qrCodeService;
        $this->middleware('auth:sanctum');
    }

    /**
     * Create Registration (RSVP)
     *
     * Registers the authenticated user for a specific event with a chosen ticket type.
     * Returns the registration details including QR code token for check-in.
     *
     * @group Registrations
     * @authenticated
     * @urlParam event integer required The ID of the event. Example: 1
     * @bodyParam ticket_id integer required The ID of the ticket to reserve. Example: 1
     * @bodyParam additional_data object optional Custom fields required by the event. Example: {"t_shirt_size": "L"}
     *
     * @response 201 {
     *   "data": {
     *     "id": 1,
     *     "status": "confirmed",
     *     "event": {"id": 1, "title": "Intro to Synthetic Biology"},
     *     "ticket": {"id": 1, "name": "General Admission"},
     *     "qr_code_token": "reg-abc-123-xyz"
     *   }
     * }
     */
    public function store(Request $request, Event $event)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'additional_data' => 'nullable|array',
        ]);

        $ticket = Ticket::findOrFail($validated['ticket_id']);

        // Block RSVP for paid tickets - users must use the purchase flow for those
        if ($ticket->price > 0) {
            return response()->json([
                'message' => 'This event requires a ticket purchase. Please complete the checkout process.'
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        try {
            $registration = $this->eventService->registerUser($event, $user, $ticket, $validated['additional_data'] ?? []);
            return new RegistrationResource($registration->load(['event', 'ticket', 'user']));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * List my registrations
     *
     * Returns a paginated list of all events the user has registered for.
     *
     * @group Registrations
     * @authenticated
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "status": "confirmed",
     *       "event": {"id": 1, "title": "Intro to Synthetic Biology"},
     *       "created_at": "2025-01-15T10:00:00Z"
     *     }
     *   ]
     * }
     */
    public function myRegistrations()
    {
        $registrations = Auth::user()->registrations()
            ->with(['event', 'ticket'])
            ->latest()
            ->paginate();

        return RegistrationResource::collection($registrations);
    }

    /**
     * Scan QR Code for Check-in
     * 
     * @group Registrations
     * @groupDescription Organizer-only endpoint to check in attendees via QR scan
     */
    public function scan(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $registration = Registration::where('event_id', $event->id)
            ->where('qr_code_token', $validated['token'])
            ->first();

        if (!$registration) {
            return response()->json(['message' => 'Invalid ticket token.'], Response::HTTP_NOT_FOUND);
        }

        if ($registration->status === 'attended') {
            return response()->json(['message' => 'User already checked in.', 'registration' => new RegistrationResource($registration)], Response::HTTP_CONFLICT);
        }

        if ($registration->status !== 'confirmed') {
            return response()->json(['message' => 'Ticket is not confirmed (Status: ' . $registration->status . ').'], Response::HTTP_FORBIDDEN);
        }

        $registration->update([
            'status' => 'attended',
            'check_in_at' => now(),
        ]);

        return response()->json([
            'message' => 'Check-in successful!',
            'registration' => new RegistrationResource($registration->load('user'))
        ]);
    }

    /**
     * Manual Check-in
     * 
     * @group Registrations
     */
    public function checkIn(Request $request, Event $event, Registration $registration)
    {
        $this->authorize('update', $event);

        if ($registration->event_id !== $event->id) {
            abort(404);
        }

        $registration->update([
            'status' => 'attended',
            'check_in_at' => now(),
        ]);

        return response()->json(['message' => 'User checked in manually.']);
    }

    /**
     * Cancel Registration
     * 
     * @group Registrations
     */
    public function destroy(Registration $registration)
    {
        if ($registration->user_id !== Auth::id()) {
            $this->authorize('delete', $registration->event);
        }

        if ($registration->status === 'attended') {
            return response()->json(['message' => 'Cannot cancel an already attended event.'], Response::HTTP_FORBIDDEN);
        }

        $registration->update(['status' => 'cancelled']);
        
        // If event has waitlist, process it
        $this->eventService->processWaitlist($registration->event);
        return response()->json(['message' => 'Registration cancelled.']);
    }

    /**
     * Get Attendance Stats
     * 
     * @group Registrations
     */
    public function getAttendanceStats(Event $event)
    {
        $this->authorize('update', $event);
        return response()->json($this->qrCodeService->getAttendanceStats($event));
    }
}
