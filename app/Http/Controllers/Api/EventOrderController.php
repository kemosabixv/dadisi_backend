<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventOrder;
use App\Services\Contracts\EventOrderServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @group Event Tickets
 *
 * APIs for purchasing event tickets and managing ticket orders.
 */
class EventOrderController extends Controller
{
    public function __construct(private EventOrderServiceContract $orderService)
    {
        // Only purchase, checkPaymentStatus, resume, and cancel endpoints allow guest access
        $this->middleware('auth')->except(['purchase', 'checkPaymentStatus', 'resume', 'cancel']);
    }

    /**
     * Purchase Event Tickets
     *
     * Purchase tickets for a paid event. Supports both authenticated users and guest checkout.
     * Returns a payment redirect URL for completing the purchase.
     *
     * @urlParam event int required The event ID. Example: 1
     * @bodyParam quantity int required Number of tickets to purchase (1-10). Example: 2
     * @bodyParam name string Guest name (required if not authenticated). Example: John Doe
     * @bodyParam email string Guest email (required if not authenticated). Example: john@example.com
     * @bodyParam phone string Contact phone number. Example: +254712345678
     * @bodyParam promo_code string Optional promo code for discount. Example: EARLY2024
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Payment initiated",
     *   "data": {
     *     "order_id": 123,
     *     "reference": "ORD-ABC123XYZ",
     *     "total_amount": 2500,
     *     "redirect_url": "https://payment-gateway.com/pay/xyz"
     *   }
     * }
     * @response 400 {"success": false, "message": "Only 5 spots available."}
     */
    public function purchase(\App\Http\Requests\Api\CreateEventOrderRequest $request, Event $event): JsonResponse
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $validated = $request->validated();
        $purchaserData = [
            'ticket_id' => $validated['ticket_id'],
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? $user?->email,
            'phone' => $validated['phone'] ?? null,
        ];
        $result = $this->orderService->createOrder(
            $event,
            $validated['quantity'],
            $purchaserData,
            $validated['promo_code'] ?? null,
            $user,
            $validated['is_waitlist_action'] ?? false
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'is_sold_out' => $result['is_sold_out'] ?? false,
            ], 400);
        }

        $order = $result['order'];
        $message = $result['payment_required'] ? 'Payment initiated' : 'Ticket confirmed';
        if ($result['is_waitlisted'] ?? false) {
            $message = 'Waitlist confirmed';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'order_id' => $order->id,
                'reference' => $order->reference,
                'total_amount' => $order->total_amount,
                'original_amount' => $order->original_amount,
                'promo_discount' => $order->promo_discount_amount,
                'subscriber_discount' => $order->subscriber_discount_amount,
                'payment_required' => $result['payment_required'],
                'redirect_url' => $result['redirect_url'] ?? null,
                'qr_code_token' => $order->qr_code_token,
                'is_waitlisted' => $result['is_waitlisted'] ?? false,
                'is_race_condition' => $result['is_race_condition'] ?? false,
            ],
        ]);
    }

    /**
     * Check Payment Status
     *
     * Check the payment status of an order by reference.
     *
     * @urlParam reference string required The order reference. Example: ORD-ABC123XYZ
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "status": "paid",
     *     "qr_code_token": "TKT-ABCDEFGH12345678"
     *   }
     * }
     */
    public function checkPaymentStatus(string $reference): JsonResponse
    {
        try {
            $data = $this->orderService->checkPaymentStatus($reference);
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }
    }

    /**
     * List My Tickets
     *
     * Returns all tickets purchased by the authenticated user.
     *
     * @authenticated
     * @queryParam status string Filter by status (pending, paid, refunded). Example: paid
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "reference": "ORD-ABC123",
     *       "event": {"id": 1, "title": "Tech Conference"},
     *       "quantity": 2,
     *       "total_amount": 5000,
     *       "status": "paid",
     *       "qr_code_token": "TKT-XYZ789"
     *     }
     *   ]
     * }
     */
    public function myTickets(Request $request): JsonResponse
    {
        try {
            $filters = ['status' => $request->input('status')];
            $result = $this->orderService->getUserOrders(Auth::user(), $filters, 20);
            return response()->json([
                'success' => true,
                'data' => \App\Http\Resources\EventOrderResource::collection($result['data']),
                'meta' => $result['meta'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tickets',
            ], 500);
        }
    }

    /**
     * Get Ticket Details
     *
     * Get details of a specific ticket order.
     *
     * @authenticated
     * @urlParam order int required The order ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "reference": "ORD-ABC123",
     *     "qr_code_token": "TKT-XYZ789",
     *     "event": {...},
     *     "quantity": 2,
     *     "total_amount": 5000,
     *     "status": "paid"
     *   }
     * }
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $orderId = $request->route('order');
            $result = $this->orderService->getOrderDetails(Auth::user(), $orderId);
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not found')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Resume Pending Payment
     */
    public function resume(string $reference): JsonResponse
    {
        try {
            $order = EventOrder::where('reference', $reference)
                ->where('status', 'pending')
                ->firstOrFail();

            $result = $this->orderService->resumeOrder($order);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'redirect_url' => $result['redirect_url'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Order not found or cannot be resumed'], 404);
        }
    }

    /**
     * Cancel Pending Order
     */
    public function cancel(string $reference): JsonResponse
    {
        try {
            $order = EventOrder::where('reference', $reference)
                ->whereIn('status', ['pending', 'waitlisted'])
                ->firstOrFail();

            $order->update(['status' => 'cancelled']);

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Order not found or cannot be cancelled'], 404);
        }
    }

    /**
     * Scan Ticket for Check-in
     *
     * Scan a ticket QR code to check in an attendee. Organizer/admin only.
     *
     * @authenticated
     * @urlParam event int required The event ID. Example: 1
     * @bodyParam qr_token string required The QR code token from the ticket. Example: TKT-ABCD1234
     *
     * @response 200 {"success": true, "message": "Check-in successful!"}
     * @response 400 {"success": false, "message": "Already checked in"}
     */
    public function scanTicket(\App\Http\Requests\Api\ScanEventTicketRequest $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);
        $validated = $request->validated();
        $result = $this->orderService->checkIn($validated['qr_token'], $event);
        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
