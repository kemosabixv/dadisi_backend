<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EventException;
use App\Http\Controllers\Controller;
use App\Http\Resources\RegistrationResource;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Ticket;
use App\Services\Contracts\EventRegistrationServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RegistrationController extends Controller
{
    public function __construct(
        private EventRegistrationServiceContract $registrationService,
        private \App\Services\Contracts\RefundServiceContract $refundService
    ) {
        $this->middleware('auth')->except([
            'store',
            'showByToken',
            'leaveWaitlist',
            'destroy',
            'cancelByToken',
            'refundRequestByToken',
        ]);
    }

    /**
     * Create Registration (RSVP)
     *
     * Registers the authenticated user for a specific event with a chosen ticket type.
     * Returns the registration details including QR code token for check-in.
     *
     * @group Registrations
     *
     * @authenticated
     *
     * @urlParam event integer required The ID of the event. Example: 1
     *
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
            $user = $request->user();

            $validated = $request->validate([
                'ticket_id' => 'required|exists:tickets,id',
                'additional_data' => 'nullable|array',
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'is_waitlist_action' => 'nullable|boolean',
            ]);

            // For guests, name and email are required
            if (! $user && (empty($validated['email']) || empty($validated['name']))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guest name and email are required for registration.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $ticket = Ticket::findOrFail($validated['ticket_id']);

            // Block RSVP for paid tickets - users must use the purchase flow for those
            if ($ticket->price > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This event requires a ticket purchase. Please complete the checkout process.',
                ], Response::HTTP_PAYMENT_REQUIRED);
            }

            $registration = $this->registrationService->registerUser(
                $user,
                $event,
                $validated,
                $validated['is_waitlist_action'] ?? false
            );

            return response()->json([
                'success' => true,
                'data' => new RegistrationResource($registration->load(['event', 'ticket', 'user'])),
                'is_waitlisted' => $registration->status === 'waitlisted',
                'is_race_condition' => $registration->is_race_condition ?? false,
            ], Response::HTTP_CREATED);
        } catch (EventException $e) {
            return $e->render();
        } catch (\App\Exceptions\EventCapacityExceededException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to register for event', [
                'error' => $e->getMessage(),
                'event_id' => $event->id,
                'user_id' => $user?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * List my registrations
     *
     * Returns a paginated list of all events the user has registered for.
     *
     * @group Registrations
     *
     * @authenticated
     *
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
                ->with(['event', 'ticket', 'order.refunds'])
                ->latest()
                ->paginate();

            return response()->json(['success' => true, 'data' => RegistrationResource::collection($registrations)]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch registrations', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);

            return response()->json(['success' => false, 'message' => 'Failed to fetch registrations'], 500);
        }
    }

    public function destroy(Request $request, EventRegistration $registration): JsonResponse
    {
        try {
            $user = $request->user();
            $confirmationCode = $request->input('confirmation_code');

            // Authorization check
            $isStaff = $user && \App\Support\AdminAccessResolver::canAccessAdmin($user);

            if (! $isStaff) {
                if ($user) {
                    if ($registration->user_id !== $user->getAuthIdentifier()) {
                        return response()->json(['success' => false, 'message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
                    }
                } else {
                    // Guest check
                    $authorized = ($confirmationCode && $registration->confirmation_code === $confirmationCode) ||
                                 ($confirmationCode && $registration->qr_code_token === $confirmationCode);

                    if (! $authorized) {
                        return response()->json(['success' => false, 'message' => 'Invalid confirmation code or token.'], Response::HTTP_FORBIDDEN);
                    }
                }
            }

            // For staff, if no reason is provided, use a generic one
            $reason = $request->input('reason');
            if ($isStaff && ! $reason) {
                $reason = 'Staff generated by '.$user->username;
            }

            $this->registrationService->cancelRegistration($user, $registration->event, $reason, $registration);

            return response()->json([
                'success' => true,
                'message' => 'Registration cancelled successfully.'.($registration->order_id ? ' Refund request submitted.' : ''),
            ]);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to cancel registration', [
                'error' => $e->getMessage(),
                'registration_id' => $registration->id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Cancellation failed.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Show Registration by Token (Public)
     */
    public function showByToken(string $token): JsonResponse
    {
        try {
            $registration = EventRegistration::where('qr_code_token', $token)
                ->with(['event', 'ticket', 'user', 'order.refunds'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => new RegistrationResource($registration),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found or invalid.',
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Leave waitlist for an event
     */
    public function leaveWaitlist(Request $request, Event $event): JsonResponse
    {
        try {
            $user = $request->user();
            $identifier = $user ? $user->getAuthIdentifier() : ($request->input('guest_email') ?: $request->input('confirmation_code'));

            if (! $identifier) {
                return response()->json(['success' => false, 'message' => 'Identification required to leave waitlist.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $success = $this->registrationService->leaveWaitlist((string) $identifier, $event);

            if ($success) {
                return response()->json(['success' => true, 'message' => 'You have left the waitlist.']);
            }

            return response()->json(['success' => false, 'message' => 'No active waitlist registration found.'], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to leave waitlist', ['error' => $e->getMessage(), 'event_id' => $event->id]);

            return response()->json(['success' => false, 'message' => 'Operation failed.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cancel Ticket/RSVP by Token (Public)
     */
    public function cancelByToken(Request $request, string $token): JsonResponse
    {
        try {
            $registration = EventRegistration::where('qr_code_token', $token)->firstOrFail();
            $user = $request->user();

            // Authorization: If registration has a user, and a user is logged in, they must match
            // If it's a guest registration, the token itself is the authorization
            if ($registration->user_id && $user && $registration->user_id !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
            }

            $reason = $request->input('reason', 'Cancelled via universal ticket page');

            $this->registrationService->cancelRegistration($user, $registration->event, $reason, $registration);

            return response()->json([
                'success' => true,
                'message' => 'Registration cancelled successfully.'.($registration->order_id ? ' Refund request submitted.' : ''),
            ]);
        } catch (\Exception $e) {
            Log::error('Token-based cancellation failed', ['error' => $e->getMessage(), 'token' => $token]);

            return response()->json(['success' => false, 'message' => 'Cancellation failed.'], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Request Refund by Token (Public)
     */
    public function refundRequestByToken(Request $request, string $token): JsonResponse
    {
        try {
            $registration = EventRegistration::where('qr_code_token', $token)->firstOrFail();

            if (! $registration->order_id) {
                return response()->json(['success' => false, 'message' => 'This registration is not linked to a paid order.'], 400);
            }

            $order = $registration->order;
            if (! $order || $order->status !== 'paid') {
                return response()->json(['success' => false, 'message' => 'No paid order found for this ticket.'], 400);
            }

            $user = $request->user();

            $validated = $request->validate([
                'reason' => 'required|string|max:500',
                'customer_notes' => 'nullable|string|max:1000',
            ]);

            // Immediate cancellation frees up the spot and triggers promotion + refund request creation
            $this->registrationService->cancelRegistration(
                $user,
                $registration->event,
                $validated['reason'],
                $registration,
                $validated['customer_notes']
            );

            return response()->json([
                'success' => true,
                'message' => 'Registration cancelled and refund request submitted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Token-based refund request failed', ['error' => $e->getMessage(), 'token' => $token]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Bulk Cancel Registrations (Admin)
     *
     * Cancels multiple registrations for a specific event simultaneously.
     * This method is restricted to administrative staff and handles capacity updates and refund requests.
     *
     * @group Registrations
     *
     * @authenticated
     *
     * @urlParam event integer required The ID of the event. Example: 1
     *
     * @bodyParam registration_ids integer[] required The IDs of the registrations to cancel. Example: [1, 2, 3]
     * @bodyParam reason string optional The reason for bulk cancellation. Example: "Event postponed"
     *
     * @response 200 {
     *   "success": true,
     *   "message": "3 registrations were cancelled successfully.",
     *   "cancelled_count": 3
     * }
     */
    public function bulkCancel(Request $request, Event $event): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Authorization check
            if (! \App\Support\AdminAccessResolver::canAccessAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Administrative access required.'
                ], Response::HTTP_FORBIDDEN);
            }

            $validated = $request->validate([
                'registration_ids' => 'required|array',
                'registration_ids.*' => 'integer|exists:event_registrations,id',
                'reason' => 'nullable|string|max:500',
            ]);

            $cancelledCount = $this->registrationService->bulkCancel(
                $event,
                $validated['registration_ids'],
                $user,
                $validated['reason']
            );

            return response()->json([
                'success' => true,
                'message' => "{$cancelledCount} registrations were cancelled successfully.",
                'cancelled_count' => $cancelledCount
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk registration cancellation failed', [
                'error' => $e->getMessage(),
                'event_id' => $event->id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk cancellation failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
