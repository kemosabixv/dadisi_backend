<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEventTicketRequest;
use App\Http\Requests\Api\UpdateEventTicketRequest;
use App\Http\Resources\TicketResource;
use App\Services\Contracts\EventTicketServiceContract;
use App\Exceptions\EventTicketException;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * TicketController
 *
 * Handles public and admin operations for event ticket tiers.
 */
class TicketController extends Controller
{
    public function __construct(private EventTicketServiceContract $ticketService)
    {
        $this->middleware('auth:sanctum')->except(['index']);
    }

    /**
     * List Tickets for Event
     *
     * @group Tickets
     * @unauthenticated
     * @urlParam event integer required The event ID or slug. Example: 5
     */
    public function index($eventSelector)
    {
        try {
            // Support both ID and Slug
            $event = is_numeric($eventSelector) 
                ? Event::findOrFail($eventSelector)
                : Event::where('slug', $eventSelector)->firstOrFail();

            $tickets = $this->ticketService->listEventTickets($event);
            
            return response()->json([
                'success' => true,
                'data' => TicketResource::collection($tickets),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
            ], 404);
        }
    }

    /**
     * Create Ticket Tier
     * 
     * @group Tickets
     * @authenticated
     */
    public function store(StoreEventTicketRequest $request, Event $event)
    {
        try {
            $this->authorize('update', $event);
            
            $ticket = $this->ticketService->createTicket(
                auth()->user(),
                $event,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'data' => new TicketResource($ticket),
                'message' => 'Ticket tier created successfully',
            ], Response::HTTP_CREATED);
        } catch (EventTicketException $e) {
            return $e->render($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket tier: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update Ticket
     * 
     * @group Tickets
     * @authenticated
     */
    public function update(UpdateEventTicketRequest $request, Ticket $ticket)
    {
        try {
            $this->authorize('update', $ticket->event);
            
            $updatedTicket = $this->ticketService->updateTicket(
                auth()->user(),
                $ticket,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'data' => new TicketResource($updatedTicket),
                'message' => 'Ticket tier updated successfully',
            ]);
        } catch (EventTicketException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket tier',
            ], 500);
        }
    }

    /**
     * Delete Ticket
     * 
     * @group Tickets
     * @authenticated
     */
    public function destroy(Ticket $ticket)
    {
        try {
            $this->authorize('update', $ticket->event);
            
            $this->ticketService->deleteTicket(auth()->user(), $ticket);

            return response()->json([
                'success' => true,
                'message' => 'Ticket tier deleted successfully',
            ], 200);
        } catch (EventTicketException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket tier: ' . $e->getMessage(),
            ], 500);
        }
    }
}
