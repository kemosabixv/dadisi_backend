<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSupportTicketRequest;
use App\Http\Requests\Api\AddSupportTicketResponseRequest;
use App\Services\Contracts\SupportTicketServiceContract;
use App\Exceptions\SupportTicketException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SupportTicketController extends Controller
{
    public function __construct(private SupportTicketServiceContract $supportService)
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List user's own tickets
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = ['user_id' => $request->user()->id];
            if ($request->has('status')) {
                $filters['status'] = $request->input('status');
            }

            $tickets = $this->supportService->listTickets($filters);
            
            return response()->json([
                'success' => true,
                'data' => $tickets,
            ]);
        } catch (SupportTicketException $e) {
            return $e->render($request);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to retrieve support tickets', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve support tickets'], 500);
        }
    }

    /**
     * Create a new support ticket
     */
    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        try {
            $ticket = $this->supportService->createTicket($request->user(), $request->validated());

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => 'Support ticket created successfully',
            ], Response::HTTP_CREATED);
        } catch (SupportTicketException $e) {
            return $e->render($request);
        }
    }

    /**
     * View ticket detail
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $ticket = $this->supportService->getTicketDetail($id);

            // Ensure user only sees their own tickets
            if ($ticket->user_id !== $request->user()->id) {
                throw SupportTicketException::unauthorized();
            }

            return response()->json([
                'success' => true,
                'data' => $ticket,
            ]);
        } catch (SupportTicketException $e) {
            return $e->render(request());
        }
    }

    /**
     * Add response to own ticket
     */
    public function addResponse(AddSupportTicketResponseRequest $request, int $id): JsonResponse
    {
        try {
            $ticket = $this->supportService->getTicketDetail($id);

            if ($ticket->user_id !== $request->user()->id) {
                throw SupportTicketException::unauthorized();
            }

            $response = $this->supportService->addResponse(
                $request->user(),
                $id,
                $request->message,
                $request->attachments ?? []
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
}
