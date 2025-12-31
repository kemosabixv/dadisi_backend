<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOrder;
use App\Models\PromoCode;
use App\Models\User;
use App\Models\Payment;
use App\Services\Contracts\EventOrderServiceContract;
use App\Services\PaymentGateway\GatewayManager;
use App\DTOs\Payments\PaymentRequestDTO;
use App\DTOs\Payments\TransactionResultDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling event ticket purchases.
 */
class EventOrderService implements EventOrderServiceContract
{
    /**
     * Create a new ticket order and initiate payment.
     *
     * @param Event $event
     * @param int $quantity
     * @param array $purchaserData User data or guest data
     * @param string|null $promoCode
     * @param User|null $user Authenticated user (null for guest)
     * @return array
     */
    public function createOrder(
        Event $event,
        int $quantity,
        array $purchaserData,
        ?string $promoCode = null,
        ?User $user = null
    ): array {
        // Validate event is purchasable
        if ($event->status !== 'published') {
            return ['success' => false, 'message' => 'Event is not available for purchase.'];
        }

        if (!$event->is_paid) {
            return ['success' => false, 'message' => 'This is a free event. Please RSVP instead.'];
        }

        // Check capacity
        $soldCount = EventOrder::where('event_id', $event->id)
            ->whereIn('status', ['paid', 'pending'])
            ->sum('quantity');

        $availableSpots = $event->capacity - $soldCount;
        if ($quantity > $availableSpots) {
            return [
                'success' => false,
                'message' => "Only {$availableSpots} spots available.",
            ];
        }

        // Calculate pricing
        $unitPrice = (float) $event->price;
        $originalAmount = $unitPrice * $quantity;
        $promoDiscountAmount = 0;
        $subscriberDiscountAmount = 0;
        $promoCodeModel = null;

        // Apply promo code if provided
        if ($promoCode) {
            $promoCodeModel = PromoCode::where('code', strtoupper($promoCode))
                ->where('is_active', true)
                ->where(function($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
                })
                ->first();

            if ($promoCodeModel) {
                // Check if promo code applies to this event
                if ($this->promoCodeAppliesToEvent($promoCodeModel, $event)) {
                    $promoDiscountAmount = $this->calculatePromoDiscount(
                        $promoCodeModel,
                        $originalAmount
                    );
                }
            }
        }

        // Apply subscriber discount if user has active subscription
        if ($user && $user->hasActiveSubscription()) {
            $discountPercent = $this->getSubscriberDiscountPercent($user);
            if ($discountPercent > 0) {
                $amountAfterPromo = $originalAmount - $promoDiscountAmount;
                $subscriberDiscountAmount = $amountAfterPromo * ($discountPercent / 100);
            }
        }

        $totalAmount = $originalAmount - $promoDiscountAmount - $subscriberDiscountAmount;
        $totalAmount = max(0, $totalAmount); // Ensure non-negative

        return DB::transaction(function () use (
            $event, $quantity, $purchaserData, $user,
            $unitPrice, $originalAmount, $totalAmount,
            $promoDiscountAmount, $subscriberDiscountAmount, $promoCodeModel
        ) {
            // Create the order
            $order = EventOrder::create([
                'event_id' => $event->id,
                'user_id' => $user?->id,
                'guest_name' => $user ? null : ($purchaserData['name'] ?? null),
                'guest_email' => $user ? null : ($purchaserData['email'] ?? null),
                'guest_phone' => $purchaserData['phone'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'original_amount' => $originalAmount,
                'promo_code_id' => $promoCodeModel?->id,
                'promo_discount_amount' => $promoDiscountAmount,
                'subscriber_discount_amount' => $subscriberDiscountAmount,
                'total_amount' => $totalAmount,
                'currency' => $event->currency ?? 'KES',
                'status' => 'pending',
            ]);

            // If total is 0 (fully discounted), mark as paid immediately
            if ($totalAmount <= 0) {
                $order->update([
                    'status' => 'paid',
                    'purchased_at' => now(),
                ]);

                // Send confirmation notification if user exists
                if ($order->user) {
                    try {
                        $order->user->notify(new \App\Notifications\TicketPurchaseConfirmation($order));
                    } catch (\Exception $e) {
                        Log::error('Failed to send ticket notification for discounted order', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                return [
                    'success' => true,
                    'order' => $order,
                    'payment_required' => false,
                    'message' => 'Ticket confirmed! (Fully discounted)',
                ];
            }

            // Initiate payment using DTO
            $paymentRequest = new PaymentRequestDTO(
                amount: (float) $totalAmount,
                currency: $order->currency ?? 'KES',
                description: "Ticket for {$event->title}",
                reference: $order->reference,
                payment_method: 'pesapal', // Default or selector
                email: $user?->email ?? $purchaserData['email'],
                phone: $purchaserData['phone'] ?? null,
                first_name: $user?->memberProfile?->first_name ?? $purchaserData['name'] ?? 'Guest',
                last_name: $user?->memberProfile?->last_name ?? '',
                payable_id: $order->id,
                payable_type: 'event_order',
                county: $event->county?->name ?? 'Nairobi' // Attribution
            );

            $paymentResult = (new GatewayManager())->initiatePayment($paymentRequest, $order);

            if (!$paymentResult->success) {
                // Payment initiation failed, mark order as failed
                $order->update(['status' => 'failed']);

                Log::error('Event order payment initiation failed', [
                    'order_id' => $order->id,
                    'error' => $paymentResult->message ?? 'Unknown error',
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to initiate payment. Please try again.',
                    'order' => $order,
                ];
            }

            // Create payment record
            Payment::create([
                'payable_type' => 'event_order',
                'payable_id' => $order->id,
                'amount' => $totalAmount,
                'currency' => $order->currency,
                'status' => 'pending',
                'gateway' => GatewayManager::getActiveGateway(),
                'transaction_id' => $paymentResult->transactionId,
                'external_reference' => $paymentResult->merchantReference,
            ]);

            return [
                'success' => true,
                'order' => $order,
                'payment_required' => true,
                'redirect_url' => $paymentResult->redirectUrl,
                'transaction_id' => $paymentResult->transactionId,
            ];
        });
    }

    /**
     * Check if a promo code applies to a specific event.
     */
    protected function promoCodeAppliesToEvent(PromoCode $promoCode, Event $event): bool
    {
        // Check if promo has event restrictions
        if ($promoCode->applicable_event_ids) {
            $eventIds = is_array($promoCode->applicable_event_ids) 
                ? $promoCode->applicable_event_ids 
                : json_decode($promoCode->applicable_event_ids, true);

            if (!empty($eventIds) && !in_array($event->id, $eventIds)) {
                return false;
            }
        }

        // Check usage limits
        if ($promoCode->max_uses && $promoCode->times_used >= $promoCode->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Calculate promo code discount amount.
     */
    protected function calculatePromoDiscount(PromoCode $promoCode, float $amount): float
    {
        if ($promoCode->type === 'percent') {
            return $amount * ($promoCode->value / 100);
        }

        // Fixed amount discount
        return min($promoCode->value, $amount);
    }

    /**
     * Get subscriber ticket discount percentage from their plan.
     */
    protected function getSubscriberDiscountPercent(User $user): float
    {
        $subscription = $user->planSubscriptions()
            ->where('ends_at', '>', now())
            ->orWhereNull('ends_at')
            ->first();

        if (!$subscription || !$subscription->plan) {
            return 0;
        }

        // Get the event_ticket_discount feature value from the plan
        return (float) $subscription->plan->getFeatureValue('event_ticket_discount', 0);
    }

    /**
     * Mark order as paid (called from webhook/callback).
     */
    public function markOrderPaid(EventOrder $order, string $transactionId): void
    {
        $order->update([
            'status' => 'paid',
            'purchased_at' => now(),
        ]);

        // Update promo code usage
        if ($order->promo_code_id) {
            PromoCode::where('id', $order->promo_code_id)
                ->increment('times_used');
        }

        // Update payment record
        Payment::where('payable_type', 'event_order')
            ->where('payable_id', $order->id)
            ->update([
                'status' => 'completed',
                'transaction_id' => $transactionId,
            ]);

        Log::info('Event order marked as paid', [
            'order_id' => $order->id,
            'transaction_id' => $transactionId,
        ]);

        // Dispatch notification if user exists
        if ($order->user) {
            try {
                $order->user->notify(new \App\Notifications\TicketPurchaseConfirmation($order));
            } catch (\Exception $e) {
                Log::error('Failed to send ticket notification after payment', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Check in an attendee using their QR code token.
     */
    public function checkIn(string $qrToken, Event $event): array
    {
        $order = EventOrder::where('qr_code_token', $qrToken)
            ->where('event_id', $event->id)
            ->first();

        if (!$order) {
            return ['success' => false, 'message' => 'Invalid ticket.'];
        }

        if ($order->status !== 'paid') {
            return ['success' => false, 'message' => 'Ticket not paid.'];
        }

        if ($order->isCheckedIn()) {
            return [
                'success' => false,
                'message' => 'Already checked in at ' . $order->checked_in_at->format('H:i'),
                'order' => $order,
            ];
        }

        $order->update(['checked_in_at' => now()]);

        return [
            'success' => true,
            'message' => 'Check-in successful!',
            'order' => $order->fresh(),
        ];
    }

    /**
     * Check payment status of an order by reference.
     */
    public function checkPaymentStatus(string $reference): array
    {
        $order = EventOrder::where('reference', $reference)->firstOrFail();

        return [
            'status' => $order->status,
            'qr_code_token' => $order->qr_code_token,
        ];
    }

    /**
     * Get order details for a specific user.
     */
    public function getOrderDetails(User $user, int $orderId): array
    {
        $order = EventOrder::where('id', $orderId)
            ->where('user_id', $user->id)
            ->with(['event', 'promoCode'])
            ->firstOrFail();

        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'qr_code_token' => $order->qr_code_token,
            'event' => $order->event,
            'quantity' => $order->quantity,
            'total_amount' => $order->total_amount,
            'status' => $order->status,
            'purchased_at' => $order->purchased_at,
        ];
    }

    /**
     * Get user's orders with filters.
     */
    public function getUserOrders(User $user, array $filters, int $perPage): array
    {
        $query = EventOrder::where('user_id', $user->id)
            ->with(['event'])
            ->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $paginated = $query->paginate($perPage);

        return [
            'data' => $paginated->items(),
            'meta' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
            ],
        ];
    }
}
