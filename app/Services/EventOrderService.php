<?php

namespace App\Services;

use App\DTOs\Payments\PaymentRequestDTO;
use App\Models\Event;
use App\Models\EventOrder;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Contracts\EventOrderServiceContract;
use App\Services\PaymentGateway\GatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Service for handling event ticket purchases.
 */
class EventOrderService implements EventOrderServiceContract
{
    /**
     * Create a new ticket order and initiate payment.
     *
     * @param  array  $purchaserData  User data or guest data
     * @param  User|null  $user  Authenticated user (null for guest)
     */
    public function createOrder(
        Event $event,
        int $quantity,
        array $purchaserData,
        ?string $promoCode = null,
        ?User $user = null,
        bool $isWaitlistAction = false
    ): array {
        // Validate event is purchasable
        if ($event->status !== 'published') {
            return ['success' => false, 'message' => 'Event is not available for purchase.'];
        }

        if (! $event->is_paid) {
            return ['success' => false, 'message' => 'This is a free event. Please RSVP instead.'];
        }

        // Validate ticket tier
        $ticketId = $purchaserData['ticket_id'] ?? null;
        if (! $ticketId) {
            return ['success' => false, 'message' => 'Ticket type is required.'];
        }

        $ticket = Ticket::where('event_id', $event->id)->where('id', $ticketId)->first();
        if (! $ticket) {
            return ['success' => false, 'message' => 'Invalid ticket type selected.'];
        }

        // Capacity check: Check if either the ticket tier or the overall event is full
        $isFull = false;

        // 1. Ticket-specific capacity check
        if ($ticket->available !== null && ($ticket->available < $quantity)) {
            $isFull = true;
        }

        // 2. Overall event capacity check (if not already full from tier check)
        if (! $isFull && $event->capacity !== null) {
            $confirmedRegistrations = \App\Models\EventRegistration::where('event_id', $event->id)
                ->where('status', 'confirmed')
                ->count();

            // Also count paid orders that haven't synchronized to registrations yet
            $paidOrderQuantity = \App\Models\EventOrder::where('event_id', $event->id)
                ->where('status', \App\Models\EventOrder::STATUS_PAID)
                ->whereDoesntHave('registrations', function($q) {
                    $q->where('status', 'confirmed');
                })
                ->sum('quantity');


            if (($confirmedRegistrations + $paidOrderQuantity + $quantity) > $event->capacity) {
                $isFull = true;
            }
        }

        // Calculate pricing based on ticket tier
        $unitPrice = (float) $ticket->price;
        $originalAmount = $unitPrice * $quantity;
        $promoDiscountAmount = 0;
        $subscriberDiscountAmount = 0;
        $promoCodeModel = null;

        // Apply promo code if provided
        if ($promoCode) {
            $promoCodeModel = PromoCode::where('code', strtoupper($promoCode))
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->first();

            if ($promoCodeModel) {
                // Check if promo code applies to this event AND ticket tier
                // Waitlist Guard: Block promo codes if joining waitlist
                if (! $isFull && $this->promoCodeAppliesToTier($promoCodeModel, $event, $ticket)) {
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

        return DB::transaction(function () use ($event, $quantity, $unitPrice, $originalAmount, $promoDiscountAmount, $subscriberDiscountAmount, $totalAmount, $isFull, $isWaitlistAction, $user, $purchaserData, $promoCodeModel, $ticket) {
            if ($isFull && ! $event->waitlist_enabled) {
                // If it was a tier-specific full status, provide a more specific message
                $message = ($ticket->available !== null && $ticket->available < $quantity)
                    ? 'This ticket tier is sold out.'
                    : 'The event is now full.';

                return [
                    'success' => false,
                    'message' => $message,
                    'is_sold_out' => true,
                ];
            }

            $status = $isFull ? EventOrder::STATUS_WAITLISTED : EventOrder::STATUS_PENDING;
            $isRaceCondition = $isFull && ! $isWaitlistAction;
            
            $waitlistPosition = null;

            if ($isFull) {
                // FIFO Position Calculation with Priority:
                // Priority users: Range 1 - 999,999
                // Normal users: Range 1,000,000+
                $hasPriority = false;
                if ($user) {
                    $subscription = $user->activeSubscription()->first();
                    $hasPriority = $subscription && $subscription->plan && $subscription->plan->getFeatureValue('waitlist_priority', false);
                }

                if ($hasPriority) {
                    $maxPriority = EventOrder::where('event_id', $event->id)
                        ->where('waitlist_position', '<', 1000000)
                        ->max('waitlist_position') ?: 0;
                    $waitlistPosition = min($maxPriority + 1, 999999);
                } else {
                    $maxNormal = EventOrder::where('event_id', $event->id)
                        ->where('waitlist_position', '>=', 1000000)
                        ->max('waitlist_position') ?: 999999;
                    $waitlistPosition = $maxNormal + 1;
                }
            }

            // Create the order
            $order = EventOrder::create([
                'event_id' => $event->id,
                'ticket_id' => $ticket->id,
                'user_id' => $user?->id,
                'guest_name' => $user ? null : ($purchaserData['name'] ?? null),
                'guest_email' => $user ? null : ($purchaserData['email'] ?? null),
                'guest_phone' => $purchaserData['phone'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'original_amount' => $originalAmount,
                'promo_code_id' => $promoDiscountAmount > 0 ? $promoCodeModel?->id : null,
                'promo_discount_amount' => $promoDiscountAmount,
                'subscriber_discount_amount' => $subscriberDiscountAmount,
                'total_amount' => $totalAmount,
                'currency' => $ticket->currency ?? 'KES',
                'status' => $status,
                'waitlist_position' => $waitlistPosition,
            ]);

            // Create corresponding registrations for visibility in admin dashboard
            for ($i = 0; $i < $quantity; $i++) {
                \App\Models\EventRegistration::create([
                    'event_id' => $event->id,
                    'user_id' => $user?->id,
                    'guest_name' => $order->guest_name,
                    'guest_email' => $order->guest_email,
                    'ticket_id' => $ticket->id,
                    'order_id' => $order->id,
                    'status' => $status,
                    'confirmation_code' => 'CONF-'.strtoupper(\Illuminate\Support\Str::random(10)),
                    'qr_code_token' => ($i === 0) ? $order->qr_code_token : \App\Models\EventRegistration::generateQrToken(),
                    'waitlist_position' => $waitlistPosition,
                ]);
            }

            if ($isFull) {
                // Send waitlist joined notification
                if ($user) {
                    try {
                        $user->notify(new \App\Notifications\EventWaitlistJoined($order));
                    } catch (\Exception $e) {
                        Log::error('Failed to send waitlist notification to user', ['error' => $e->getMessage()]);
                    }
                } elseif ($order->guest_email) {
                    try {
                        Notification::route('mail', $order->guest_email)
                            ->notify(new \App\Notifications\EventWaitlistJoined($order));
                    } catch (\Exception $e) {
                        Log::error('Failed to send waitlist notification to guest', ['error' => $e->getMessage()]);
                    }
                }

                return [
                    'success' => true,
                    'order' => $order,
                    'payment_required' => false,
                    'message' => $isRaceCondition
                        ? 'While you were checking out, the last spot was taken. You have been added to the waitlist.'
                        : 'You have been added to the waitlist.',
                    'is_waitlisted' => true,
                    'is_race_condition' => $isRaceCondition,
                ];
            }

            // If total is 0 (fully discounted), mark as paid immediately
            if ($totalAmount <= 0) {
                // Decrement ticket availability
                $ticket->decrement('available', $quantity);

                $order->update([
                    'status' => 'paid',
                    'purchased_at' => now(),
                ]);

                // Sync registrations
                \App\Models\EventRegistration::where('order_id', $order->id)
                    ->update([
                        'status' => 'confirmed',
                        'waitlist_position' => null,
                    ]);

                // Send confirmation notification
                if ($order->user) {
                    try {
                        $order->user->notify(new \App\Notifications\TicketPurchaseConfirmation($order));
                    } catch (\Exception $e) {
                        Log::error('Failed to send ticket notification for discounted order', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } elseif ($order->guest_email) {
                    try {
                        \Illuminate\Support\Facades\Mail::to($order->guest_email)->send(
                            new \App\Mail\GuestEventTicket($order)
                        );
                    } catch (\Exception $e) {
                        Log::error('Failed to send guest ticket notification for discounted order', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
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
                description: "Ticket for {$event->title} ({$ticket->name})",
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

            $paymentResult = (new GatewayManager)->initiatePayment($paymentRequest, $order);

            if (! $paymentResult->success) {
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
                'payer_id' => $order->user_id,
                'amount' => $totalAmount,
                'currency' => $order->currency,
                'status' => 'pending',
                'gateway' => GatewayManager::getActiveGateway(),
                'transaction_id' => $paymentResult->transactionId,
                'external_reference' => $paymentResult->merchantReference,
                'order_reference' => $order->reference,
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
     * Check if a promo code applies to a specific event and ticket tier.
     */
    protected function promoCodeAppliesToTier(PromoCode $promoCode, Event $event, Ticket $ticket): bool
    {
        // Check if promo has event restrictions
        if ($promoCode->event_id && $promoCode->event_id !== $event->id) {
            return false;
        }

        // Check if promo has ticket tier restrictions
        if ($promoCode->ticket_id && $promoCode->ticket_id !== $ticket->id) {
            return false;
        }

        // Check usage limits
        if ($promoCode->usage_limit && $promoCode->used_count >= $promoCode->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Calculate promo code discount amount.
     */
    protected function calculatePromoDiscount(PromoCode $promoCode, float $amount): float
    {
        if ($promoCode->discount_type === 'percentage') {
            return $amount * ($promoCode->discount_value / 100);
        }

        // Fixed amount discount
        return min($promoCode->discount_value, $amount);
    }

    /**
     * Get subscriber ticket discount percentage from their plan.
     */
    protected function getSubscriberDiscountPercent(User $user): float
    {
        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('ends_at', '>', now())
                    ->orWhereNull('ends_at');
            })
            ->first();

        if (! $subscription || ! $subscription->plan) {
            return 0;
        }

        // Get the ticket_discount_percent feature value from the plan
        return (float) $subscription->plan->getFeatureValue('ticket_discount_percent', 0);
    }

    /**
     * Mark order as paid (called from webhook/callback).
     */
    public function markOrderPaid(EventOrder $order, string $transactionId): void
    {
        $isPromoted = $order->waitlist_position === -1;

        $order->update([
            'status' => EventOrder::STATUS_PAID,
            'purchased_at' => now(),
            'waitlist_position' => null, // Clear the promotion flag
        ]);

        // Sync registrations to confirmed
        \App\Models\EventRegistration::where('order_id', $order->id)
            ->update([
                'status' => 'confirmed',
                'waitlist_position' => null,
            ]);

        // Decrement ticket availability if linked AND not already decremented via promotion
        if ($order->ticket && ! $isPromoted) {
            $order->ticket->decrement('available', $order->quantity);
        }

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

        // Commit BEFORE sending notifications to avoid race conditions with queue or missing data
        DB::commit();

        // Dispatch notification
        if ($order->user) {
            try {
                $order->user->notify(new \App\Notifications\TicketPurchaseConfirmation($order));
            } catch (\Exception $e) {
                Log::error('Failed to send ticket notification after payment', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } elseif ($order->guest_email) {
            try {
                \Illuminate\Support\Facades\Mail::to($order->guest_email)->send(
                    new \App\Mail\GuestEventTicket($order)
                );
            } catch (\Exception $e) {
                Log::error('Failed to send guest ticket notification after payment', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
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

        if (! $order) {
            return ['success' => false, 'message' => 'Invalid ticket.'];
        }

        if ($order->status !== 'paid') {
            return ['success' => false, 'message' => 'Ticket not paid.'];
        }

        if ($order->isCheckedIn()) {
            return [
                'success' => false,
                'message' => 'Already checked in at '.$order->checked_in_at->format('H:i'),
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
        $order = EventOrder::where('reference', $reference)->with('event')->firstOrFail();

        return [
            'status' => $order->status,
            'paid' => $order->status === 'paid',
            'qr_code_token' => $order->qr_code_token,
            'event' => $order->event ? [
                'id' => $order->event->id,
                'title' => $order->event->title,
                'starts_at' => $order->event->starts_at,
            ] : null,
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
            ->with(['event', 'refunds'])
            ->latest();

        if (! empty($filters['status'])) {
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

    /**
     * Resume a pending event order.
     */
    public function resumeOrder(EventOrder $order): array
    {
        if ($order->status !== 'pending') {
            return ['success' => false, 'message' => 'Only pending orders can be resumed.'];
        }

        return DB::transaction(function () use ($order) {
            $event = $order->event;
            $ticket = $order->ticket;

            if (! $event || ! $ticket) {
                return ['success' => false, 'message' => 'Associated event or ticket not found.'];
            }

            // Re-initiate payment using DTO
            $paymentRequest = new PaymentRequestDTO(
                amount: (float) $order->total_amount,
                currency: $order->currency ?? 'KES',
                description: "Ticket for {$event->title} ({$ticket->name})",
                reference: $order->reference,
                payment_method: 'pesapal',
                email: $order->user?->email ?? $order->guest_email,
                phone: $order->guest_phone ?? null,
                first_name: $order->user?->memberProfile?->first_name ?? $order->guest_name ?? 'Guest',
                last_name: $order->user?->memberProfile?->last_name ?? '',
                payable_id: $order->id,
                payable_type: 'event_order',
                payer_id: $order->user_id,
                county: $event->county?->name ?? 'Nairobi'
            );

            $paymentResult = (new GatewayManager)->initiatePayment($paymentRequest, $order);

            if (! $paymentResult->success) {
                Log::error('Event order payment resumption failed', [
                    'order_id' => $order->id,
                    'error' => $paymentResult->message ?? 'Unknown error',
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to initiate payment. Please try again.',
                ];
            }

            // Create payment record
            Payment::create([
                'payable_type' => 'event_order',
                'payable_id' => $order->id,
                'payer_id' => $order->user_id,
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'status' => 'pending',
                'gateway' => GatewayManager::getActiveGateway(),
                'transaction_id' => $paymentResult->transactionId,
                'external_reference' => $paymentResult->merchantReference,
                'order_reference' => $order->reference,
            ]);

            return [
                'success' => true,
                'redirect_url' => $paymentResult->redirectUrl,
                'transaction_id' => $paymentResult->transactionId,
            ];
        });
    }
}
