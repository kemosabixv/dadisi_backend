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
     * @group Registrations
     */
    public function store(Request $request, Event $event)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'additional_data' => 'nullable|array',
        ]);

        $ticket = Ticket::findOrFail($validated['ticket_id']);

        try {
            $registration = $this->eventService->registerUser($event, $user, $ticket, $validated['additional_data'] ?? []);
            return new RegistrationResource($registration->load(['event', 'ticket', 'user']));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * List user's registrations
     * 
     * @group Registrations
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
