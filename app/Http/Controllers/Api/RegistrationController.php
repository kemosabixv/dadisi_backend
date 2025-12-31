<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EventException;
use App\Http\Controllers\Controller;
use App\Http\Resources\RegistrationResource;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Ticket;
use App\Services\Contracts\EventRegistrationServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RegistrationController extends Controller
{
    public function __construct(
        private EventRegistrationServiceContract $registrationService
    ) {
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
    public function store(Request $request, Event $event): JsonResponse
    {
        try {
            $user = Auth::user();

            $validated = $request->validate([
                'ticket_id' => 'required|exists:tickets,id',
                'additional_data' => 'nullable|array',
            ]);

            $ticket = Ticket::findOrFail($validated['ticket_id']);

            // Block RSVP for paid tickets - users must use the purchase flow for those
            if ($ticket->price > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This event requires a ticket purchase. Please complete the checkout process.'
                ], Response::HTTP_PAYMENT_REQUIRED);
            }

            $registration = $this->registrationService->registerUser(
                $user, 
                $event, 
                $validated
            );

            return response()->json([
                'success' => true,
                'data' => new RegistrationResource($registration->load(['event', 'ticket', 'user']))
            ], Response::HTTP_CREATED);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to register for event', [
                'error' => $e->getMessage(), 
                'event_id' => $event->id, 
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'success' => false, 
                'message' => 'Registration failed: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
    public function myRegistrations(): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $registrations = $user->registrations()
                ->with(['event', 'ticket'])
                ->latest()
                ->paginate();

            return response()->json(['success' => true, 'data' => RegistrationResource::collection($registrations)]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch registrations', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch registrations'], 500);
        }
    }

    public function destroy(EventRegistration $registration): JsonResponse
    {
        try {
            $user = Auth::user();

            // Authorization check
            if ($registration->user_id !== $user->getAuthIdentifier()) {
                $this->authorize('delete', $registration->event);
            }

            $this->registrationService->cancelRegistration($user, $registration->event);

            return response()->json([
                'success' => true, 
                'message' => 'Registration cancelled successfully.'
            ]);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to cancel registration', [
                'error' => $e->getMessage(), 
                'registration_id' => $registration->id, 
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'success' => false, 
                'message' => 'Cancellation failed.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
