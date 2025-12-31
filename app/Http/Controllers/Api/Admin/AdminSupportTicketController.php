<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddSupportTicketResponseRequest;
use App\Http\Requests\Api\AssignSupportTicketRequest;
use App\Http\Requests\Api\UpdateSupportTicketStatusRequest;
use App\Services\Contracts\SupportTicketServiceContract;
use App\Exceptions\SupportTicketException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminSupportTicketController extends Controller
{
    public function __construct(private SupportTicketServiceContract $supportService)
    {
        $this->middleware(['auth:sanctum', 'admin']);
    }

    /**
     * List all support tickets (Admin)
     */
    public function index(Request $request)
    {
        $filters = $request->only(['status', 'user_id', 'assigned_to', 'priority']);
        $tickets = $this->supportService->listTickets($filters);
        
        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    /**
     * View ticket detail (Admin)
     */
    public function show(int $id)
    {
        try {
            $ticket = $this->supportService->getTicketDetail($id);

            return response()->json([
                'success' => true,
                'data' => $ticket,
            ]);
        } catch (SupportTicketException $e) {
            return $e->render(request());
        }
    }

    /**
     * Add response (Admin/Staff)
     */
    public function addResponse(AddSupportTicketResponseRequest $request, int $id)
    {
        try {
            $response = $this->supportService->addResponse(
                auth()->user(),
                $id,
                $request->message,
                $request->attachments ?? [],
                $request->is_internal ?? false
            );

            return response()->json([
                'success' => true,
                'data' => $response,
                'message' => 'Response added successfully',
            ]);
        } catch (SupportTicketException $e) {
            return $e->render($request);
        }
    }

    /**
     * Assign ticket to staff
     */
    public function assign(AssignSupportTicketRequest $request, int $id)
    {
        try {
            $ticket = $this->supportService->assignTicket(auth()->user(), $id, $request->assigned_to);

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => 'Ticket assigned successfully',
            ]);
        } catch (SupportTicketException $e) {
            return $e->render($request);
        }
    }

    /**
     * Update ticket status
     */
    public function updateStatus(UpdateSupportTicketStatusRequest $request, int $id)
    {
        try {
            $ticket = $this->supportService->updateStatus(auth()->user(), $id, $request->status);

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => 'Status updated successfully',
            ]);
        } catch (SupportTicketException $e) {
            return $e->render($request);
        }
    }
}
