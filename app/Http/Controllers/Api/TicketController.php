<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TicketController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index']);
    }

    /**
     * List Tickets for Event
     *
     * @group Tickets
     * @unauthenticated
     * @urlParam event integer required The event ID. Example: 5
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Early Bird",
     *       "price": 1000,
     *       "currency": "KES"
     *     }
     *   ]
     * }
     */
    public function index(Event $event)
    {
        $tickets = $event->tickets()->where('is_active', true)->orderBy('sort_order')->get();
        return TicketResource::collection($tickets);
    }

    /**
     * Create Ticket Tier
     * 
     * @group Tickets
     */
    public function store(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|in:KES,USD',
            'quantity' => 'nullable|integer|min:1',
            'order_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $ticket = $event->tickets()->create($validated);

        return new TicketResource($ticket);
    }

    /**
     * Update Ticket
     * 
     * @group Tickets
     */
    public function update(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket->event);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|in:KES,USD',
            'quantity' => 'nullable|integer|min:1',
            'order_limit' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $ticket->update($validated);

        return new TicketResource($ticket);
    }

    /**
     * Delete Ticket
     * 
     * @group Tickets
     */
    public function destroy(Ticket $ticket)
    {
        $this->authorize('update', $ticket->event);
        
        $ticket->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
