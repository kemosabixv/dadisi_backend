<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventOrder;
use App\Models\Donation;
use App\Models\LabBooking;
use App\Models\PlanSubscription;
use App\Models\Payment;
use App\Services\Contracts\RefundServiceContract;
use App\Services\Contracts\EventRegistrationServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Public Refund Controller
 * 
 * Handles refund requests from the public (users and guests).
 */
class RefundController extends Controller
{
    public function __construct(
        private RefundServiceContract $refundService,
        private EventRegistrationServiceContract $registrationService
    ) {}

    /**
     * Submit a refund request (Public)
     * 
     * Allows guests or authenticated users to submit a refund request.
     * Guests must provide their order reference and confirmation email.
     * 
     * @group Refunds
     * @bodyParam order_reference string required The order or donation reference.
     * @bodyParam email string required The email used for the order/donation.
     * @bodyParam reason string required The reason for the refund.
     * @bodyParam customer_notes string optional Additional details.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_reference' => 'required|string',
                'email' => 'required|email',
                'reason' => 'required|string|max:500',
                'customer_notes' => 'nullable|string|max:1000',
            ]);

            // Find the refundable entity (EventOrder or Donation) by reference
            $refundable = null;
            $refundableType = null;

            // Try EventOrder first
            $order = EventOrder::where('reference', $validated['order_reference'])->first();
            if ($order) {
                if ($order->guest_email !== $validated['email'] && ($order->user && $order->user->email !== $validated['email'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The provided email does not match our records for this reference.'
                    ], Response::HTTP_FORBIDDEN);
                }
                
                // For EventOrders, we want to trigger the full cancellation flow 
                // which includes waitlist promotion and notifications.
                $registrations = $order->registrations;
                if ($registrations->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No registrations found for this order.'
                    ], Response::HTTP_NOT_FOUND);
                }

                $user = $request->user();
                
                // Cancel all registrations associated with the order.
                // cancelRegistration() internally handles the refund request creation for paid orders.
                foreach ($registrations as $registration) {
                    if ($registration->status !== 'cancelled') {
                        $this->registrationService->cancelRegistration(
                            $user, 
                            $order->event, 
                            $validated['reason'], 
                            $registration, 
                            $validated['customer_notes']
                        );
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Refund request submitted successfully and is under review.',
                    'data' => [
                        'id' => $order->id,
                        'status' => 'pending'
                    ]
                ]);
            }

            // Try LabBooking by receipt_number
            $labBooking = LabBooking::where('receipt_number', $validated['order_reference'])->first();
            if ($labBooking) {
                // Verify email matches the booker (registered user or guest)
                $emailMatch = false;
                if ($labBooking->user && $labBooking->user->email === $validated['email']) {
                    $emailMatch = true;
                } elseif ($labBooking->guest_email === $validated['email']) {
                    $emailMatch = true;
                }

                if (!$emailMatch) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The provided email does not match our records for this reference.'
                    ], Response::HTTP_FORBIDDEN);
                }

                $refund = $this->refundService->submitRefundRequest(
                    'LabBooking',
                    $labBooking->id,
                    $validated['reason'],
                    $validated['customer_notes'] ?? null
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Refund request submitted successfully and is under review.',
                    'data' => [
                        'id' => $refund->id,
                        'status' => $refund->status
                    ]
                ]);
            }

            // Try Payment reference (for Subscriptions or other domains)
            $payment = Payment::where('transaction_id', $validated['order_reference'])
                ->orWhere('external_reference', $validated['order_reference'])
                ->first();
                
            if ($payment && $payment->payable instanceof PlanSubscription) {
                $subscription = $payment->payable;
                
                // Verify email
                if ($subscription->user && $subscription->user->email !== $validated['email']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The provided email does not match our records for this reference.'
                    ], Response::HTTP_FORBIDDEN);
                }

                $refund = $this->refundService->submitRefundRequest(
                    'PlanSubscription',
                    $subscription->id,
                    $validated['reason'],
                    $validated['customer_notes'] ?? null
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Refund request submitted successfully and is under review.',
                    'data' => [
                        'id' => $refund->id,
                        'status' => $refund->status
                    ]
                ]);
            }

            // Try Donation (non-refundable, but show helpful message)
            $donation = Donation::where('reference', $validated['order_reference'])->first();
            if ($donation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donations are non-refundable. Please contact support for exceptional cases.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid order or booking reference.'
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            Log::error('Public refund request failed', [
                'error' => $e->getMessage(),
                'reference' => $request->input('order_reference')
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Refund request failed: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
