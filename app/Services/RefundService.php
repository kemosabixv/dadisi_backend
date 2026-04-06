<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\EventOrder;
use App\Models\LabBooking;
use App\Models\Payment;
use App\Models\PlanSubscription;
use App\Models\Refund;
use App\Services\Contracts\NotificationServiceContract;
use App\Services\Contracts\RefundServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RefundService
 *
 * Implements refund business logic.
 */
class RefundService implements RefundServiceContract
{
    /**
     * Create a new RefundService instance.
     */
    public function __construct(
        private \App\Services\PaymentGateway\GatewayManager $gatewayManager,
        private NotificationServiceContract $notificationService
    ) {}

    /**
     * {@inheritDoc}
     */
    public function listRefunds(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Refund::with(['payment', 'processor'])
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['reason']), fn ($q) => $q->where('reason', $filters['reason']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $q->whereHas('payment', function ($pq) use ($filters) {
                    $pq->where('external_reference', 'like', "%{$filters['search']}%");
                });
            })
            ->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function submitRefundRequest(
        string $refundableType,
        int $refundableId,
        string $reason,
        ?string $customerNotes = null,
        ?float $amount = null
    ): Refund {
        $modelClass = str_contains($refundableType, '\\') ? $refundableType : 'App\\Models\\'.$refundableType;

        if (! class_exists($modelClass)) {
            // Try looking it up via Relation mapping if it's not a full class name
            $mappedClass = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($refundableType);
            if ($mappedClass) {
                $modelClass = $mappedClass;
            } else {
                throw new \Exception("Invalid refundable type: {$refundableType}");
            }
        }

        $refundable = $modelClass::findOrFail($refundableId);

        $refund = match ($modelClass) {
            EventOrder::class => $this->requestEventOrderRefund($refundable, $reason, $customerNotes, $amount),
            Donation::class => $this->requestDonationRefund($refundable, $reason, $customerNotes),
            PlanSubscription::class => $this->requestSubscriptionRefund($refundable, $reason, $customerNotes, $amount),
            LabBooking::class => $this->requestLabBookingRefund($refundable, $reason, $customerNotes),
            default => throw new \Exception("Refunds are not supported for type: {$refundableType}"),
        };

        // Notify staff of the new request
        $this->notifyStaff(new \App\Notifications\RefundRequestSubmitted($refund));

        // Notify payer of the new request
        $this->notifyPayer($refund, new \App\Notifications\RefundRequestSubmittedToPayer($refund));

        return $refund;
    }

    /**
     * {@inheritDoc}
     */
    public function requestEventOrderRefund(
        EventOrder $order,
        string $reason,
        ?string $customerNotes = null,
        ?float $amount = null
    ): Refund {
        // Validate order can be refunded
        // It must have been paid. If it's now 'cancelled', we check if it WAS paid.
        // For simplicity, we assume if it's 'cancelled' in this flow, it was paid.
        $wasPaid = $order->isPaid() || $order->status === 'cancelled';
        
        if (! $wasPaid) {
            throw new \Exception('Only paid orders can be refunded.');
        }

        // Check if there's already a pending refund
        $existingRefund = Refund::where('refundable_type', $order->getMorphClass())
            ->where('refundable_id', $order->id)
            ->whereIn('status', [Refund::STATUS_PENDING, Refund::STATUS_APPROVED, Refund::STATUS_PROCESSING])
            ->first();

        if ($existingRefund) {
            throw new \Exception('A refund is already pending for this order.');
        }

        // 4. Ensure order is cancelled
        if ($order->status !== 'cancelled' && $order->status !== 'refunded') {
            throw new \Exception('Event registration must be cancelled before a refund can be initiated.');
        }

        // Check cancellation deadline (Skip if requested by admin in admin panel context)
        // Note: For now we'll implement the check globally, and the UI will prevent users.
        // If staff uses the API directly, we might want an override flag, but let's stick to the plan.
        $deadlineDays = (int) \App\Models\SystemSetting::where('key', 'event_cancellation_deadline_days')->value('value') ?: 7;
        $eventStart = $order->event?->starts_at;

        if ($eventStart->subDays($deadlineDays)->isPast()) {
            // Ideally we'd have a way to check if current user is admin, but service should be agnostic.
            // Admin overrides are usually handled in the controller by bypassing certain checks if needed.
            // For now, we'll allow the creation but keep it pending for staff review.
        }

        // Get the payment
        $payment = $order->payment;
        if (! $payment) {
            throw new \Exception('No payment found for this order.');
        }

        // Refund is always full
        $refundAmount = $payment->amount;

        return Refund::create([
            'refundable_type' => $order->getMorphClass(),
            'refundable_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => $refundAmount,
            'currency' => $order->currency,
            'original_amount' => $payment->amount,
            'status' => Refund::STATUS_PENDING,
            'reason' => $reason,
            'customer_notes' => $customerNotes,
            'gateway' => $payment->gateway,
            'requested_at' => now(),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function requestDonationRefund(
        Donation $donation,
        string $reason,
        ?string $customerNotes = null
    ): Refund {
        // Donations are non-refundable as per requirements
        throw new \Exception('Donations are non-refundable. Please contact support for exceptional cases.');
    }

    /**
     * Request a refund for a subscription.
     */
    public function requestSubscriptionRefund(
        PlanSubscription $subscription,
        string $reason,
        ?string $customerNotes = null,
        ?float $amount = null
    ): Refund {
        // 1. Validate payment existence and status
        $payment = $subscription->payments()->where('status', 'paid')->latest()->first();
        if (! $payment || ! $payment->isPaid()) {
            throw new \Exception('Only paid subscriptions can be refunded.');
        }

        // 2. Check for existing pending/approved refunds
        $existingRefund = Refund::where('refundable_type', $subscription->getMorphClass())
            ->where('refundable_id', $subscription->id)
            ->whereIn('status', [Refund::STATUS_PENDING, Refund::STATUS_APPROVED, Refund::STATUS_PROCESSING])
            ->first();

        if ($existingRefund) {
            throw new \Exception('A refund is already pending for this subscription.');
        }

        // 3. Validate eligibility window (Dynamic Settings)
        $daysSincePayment = $payment->paid_at->diffInDays(now());

        $monthlyThreshold = (int) \App\Models\SystemSetting::where('key', 'subscription_refund_threshold_monthly_days')->value('value') ?: 14;
        $yearlyThreshold = (int) \App\Models\SystemSetting::where('key', 'subscription_refund_threshold_yearly_days')->value('value') ?: 90;

        $isMonthly = $subscription->plan?->invoice_interval === 'month';
        $threshold = $isMonthly ? $monthlyThreshold : $yearlyThreshold;

        if ($daysSincePayment > $threshold) {
            throw new \Exception("Refund eligibility period has expired ({$threshold} days since payment).");
        }

        // 4. Ensure subscription is cancelled or expired
        if (! $subscription->canceled() && $subscription->status !== 'cancelled' && $subscription->status !== 'expired') {
            throw new \Exception('Subscription must be cancelled or expired before a refund can be initiated.');
        }

        // Refund is always full
        $refundAmount = (float) $payment->amount;

        return Refund::create([
            'refundable_type' => $subscription->getMorphClass(),
            'refundable_id' => $subscription->id,
            'payment_id' => $payment->id,
            'amount' => $refundAmount,
            'currency' => $payment->currency,
            'original_amount' => $payment->amount,
            'status' => Refund::STATUS_PENDING,
            'reason' => $reason,
            'customer_notes' => $customerNotes,
            'gateway' => $payment->gateway,
            'requested_at' => now(),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function requestLabBookingRefund(
        LabBooking $booking,
        string $reason,
        ?string $customerNotes = null
    ): Refund {
        $preview = $this->getLabBookingRefundPreview($booking);
        if (! $preview['is_eligible']) {
            throw new \Exception($preview['explanation'] ?? 'This booking is not eligible for a refund.');
        }

        // 4. Ensure booking is cancelled or expired
        if ($booking->status !== 'cancelled' && $booking->status !== 'expired' && $booking->status !== 'rejected') {
            throw new \Exception('Lab booking must be cancelled or rejected before a refund can be initiated.');
        }

        $payment = $booking->payment()->where('status', 'paid')->first() ?? $booking->payment;

        return Refund::create([
            'refundable_type' => $booking->getMorphClass(),
            'refundable_id' => $booking->id,
            'payment_id' => $payment->id,
            'amount' => $preview['amount'],
            'currency' => $preview['currency'],
            'original_amount' => $preview['original_amount'] ?? $payment->amount,
            'status' => Refund::STATUS_PENDING,
            'reason' => $reason ?? 'cancellation',
            'customer_notes' => $customerNotes,
            'gateway' => $payment->gateway,
            'requested_at' => now(),
            'metadata' => [
                'series_id' => $booking->booking_series_id,
                'is_full_refund' => $preview['is_full_refund'],
                'explanation' => $preview['explanation'],
                'attended_slots' => $preview['attended_slots'] ?? 0,
                'refundable_slots' => $preview['refundable_slots'] ?? 0,
            ],
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getLabBookingRefundPreview(LabBooking $booking): array
    {
        $payment = $booking->payment()->where('status', 'paid')->first() ?? $booking->payment;
        if (! $payment || ! $payment->isPaid()) {
            return [
                'amount' => 0,
                'is_eligible' => false,
                'is_full_refund' => false,
                'explanation' => 'Booking has no paid payment.',
                'currency' => 'KES',
            ];
        }

        // Check for existing pending refund
        $existingRefund = Refund::where('refundable_type', $booking->getMorphClass())
            ->where('refundable_id', $booking->id)
            ->whereIn('status', [Refund::STATUS_PENDING, Refund::STATUS_APPROVED, Refund::STATUS_PROCESSING])
            ->first();

        if ($existingRefund) {
            return [
                'amount' => 0,
                'is_eligible' => false,
                'is_full_refund' => false,
                'explanation' => 'A refund is already pending for this lab booking.',
                'currency' => $payment->currency ?? 'KES',
            ];
        }

        $bookings = $booking->bookingSeries
            ? $booking->bookingSeries->bookings()->where('status', '!=', LabBooking::STATUS_REJECTED)->get()
            : collect([$booking]);

        $earliestSlot = $bookings->min('starts_at');
        $isFullRefundWindow = $earliestSlot && now()->addHours(24)->lt($earliestSlot);

        $paymentMethod = $payment->method ?? $payment->payment_method ?? 'card';
        $isMpesa = stripos($paymentMethod, 'mpesa') !== false;

        $attended = $bookings->filter(fn ($b) => $b->checked_in_at !== null || $b->status === LabBooking::STATUS_COMPLETED);
        $totalOriginalAmount = (float) $payment->amount;

        $refundAmount = 0;
        $explanation = '';

        // PRD Section 10: Money Refund Rules (No 24h requirement)
        if ($isMpesa) {
            // M-Pesa: Full or None
            if ($attended->isEmpty()) {
                $refundAmount = $totalOriginalAmount;
                $explanation = 'PRD Rule: Full refund eligible for M-Pesa (No attended slots).';
            } else {
                $refundAmount = 0;
                $explanation = 'PRD Rule: M-Pesa refunds are forfeited if ANY slot in the booking was attended.';
            }
        } else {
            // Card: Partial Allowed (Sum of No-show + Future slots)
            $refundable = $bookings->filter(fn ($b) => $b->checked_in_at === null && $b->status !== LabBooking::STATUS_COMPLETED && $b->starts_at->isFuture());
            
            // Calculate total hours to find the 'per-hour' cost from the original payment
            $totalHours = $bookings->sum(fn ($b) => $b->duration_hours);
            $refundableHours = $refundable->sum(fn ($b) => $b->duration_hours);

            if ($totalHours > 0) {
                // Exact partial calculation without undocumented penalties
                $refundAmount = ($refundableHours / $totalHours) * $totalOriginalAmount;
            }

            if ($refundAmount >= $totalOriginalAmount) {
                $explanation = 'PRD Rule: Full refund eligible for all non-attended slots (Card).';
            } elseif ($refundAmount > 0) {
                $explanation = 'PRD Rule: Partial refund eligible for non-attended future slots (Card).';
            } else {
                $explanation = 'PRD Rule: No future/no-show slots available for refund.';
            }
        }

        return [
            'amount' => round((float) $refundAmount, 2),
            'original_amount' => $totalOriginalAmount,
            'currency' => $payment->currency ?? 'KES',
            'is_eligible' => $refundAmount > 0,
            'is_full_refund' => $refundAmount >= $totalOriginalAmount,
            'explanation' => $explanation,
            'payment_method' => $paymentMethod,
            'transaction_id' => $payment->transaction_id ?? $payment->external_reference,
            'booking_reference' => $booking->booking_reference,
            'attended_slots' => $attended->count(),
            'refundable_slots' => $bookings->count() - $attended->count(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function approveRefund(Refund $refund, Authenticatable $admin, ?string $adminNotes = null): Refund
    {
        if (! $refund->isPending()) {
            throw new \Exception('Only pending refunds can be approved.');
        }

        $refund->approve($admin);

        if ($adminNotes) {
            $refund->update(['admin_notes' => $adminNotes]);
        }

        // Notify user/guest
        if ($refund->refundable instanceof \App\Models\LabBooking) {
            $this->notifyPayer($refund, new \App\Notifications\LabBookingRefundApproved($refund->refundable, $refund));
        } else {
            $this->notifyPayer($refund, new \App\Notifications\RefundRequestApproved($refund));
        }

        Log::info('Refund approved', [
            'refund_id' => $refund->id,
            'approved_by' => $admin->getAuthIdentifier(),
        ]);

        return $refund->fresh();
    }

    /**
     * {@inheritDoc}
     */
    public function rejectRefund(Refund $refund, Authenticatable $admin, ?string $reason = null): Refund
    {
        if (! $refund->isPending()) {
            throw new \Exception('Only pending refunds can be rejected.');
        }

        $refund->reject($admin, $reason);

        // Notify user/guest
        if ($refund->refundable instanceof \App\Models\LabBooking) {
            $this->notifyPayer($refund, new \App\Notifications\LabBookingRefundRejected($refund->refundable, $refund, $reason));
        } else {
            $this->notifyPayer($refund, new \App\Notifications\RefundRequestRejected($refund, $reason));
        }

        Log::info('Refund rejected', [
            'refund_id' => $refund->id,
            'rejected_by' => $admin->getAuthIdentifier(),
            'reason' => $reason,
        ]);

        return $refund->fresh();
    }

    /**
     * {@inheritDoc}
     */
    public function processRefund(Refund $refund): Refund
    {
        if (! $refund->canBeProcessed()) {
            throw new \Exception("Refund cannot be processed in '{$refund->status}' status.");
        }

        try {
            DB::beginTransaction();

            $refund->markProcessing();

            // For mock/local environment, auto-complete the refund EXCEPT for gateways that need real sandbox testing (Pesapal)
            $isDevEnv = in_array(app()->environment(), ['local', 'testing', 'staging']);
            if ($refund->gateway === 'mock' || ($isDevEnv && $refund->gateway !== 'pesapal')) {
                $this->completeRefund($refund);
            } else {
                // For real gateways (Pesapal), initiate refund via gateway
                $this->initiateGatewayRefund($refund);
            }

            DB::commit();

            Log::info('Refund processed', [
                'refund_id' => $refund->id,
                'amount' => $refund->amount,
                'status' => $refund->status,
            ]);

            return $refund->fresh();

        } catch (\Exception $e) {
            DB::rollBack();

            $refund->markFailed(['error' => $e->getMessage()]);

            Log::error('Refund processing failed', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Complete a refund and update related records
     */
    protected function completeRefund(Refund $refund): void
    {
        // Mark refund as completed
        $refund->markCompleted('MOCK-'.uniqid(), [
            'processed_at' => now()->toISOString(),
            'mock' => true,
        ]);

        // Update the refundable entity
        $refundable = $refund->refundable;

        if ($refundable instanceof EventOrder) {
            $refundable->update(['status' => 'refunded']);

            // Restore promo code usage if full refund
            if ($refundable->promo_code_id) {
                $refundable->promoCode()->increment('used_count', -1);
            }

            // Also mark registrations as cancelled if not already
            $refundable->registrations()->update(['status' => 'cancelled']);

            // Promotion Logic: Spots opened up via refund!
            app(\App\Services\Contracts\EventRegistrationServiceContract::class)->promoteWaitlistEntries($refundable->event);
        } elseif ($refundable instanceof Donation) {
            $refundable->update(['status' => 'refunded']);
        } elseif ($refundable instanceof PlanSubscription) {
            $refundable->update(['status' => 'refunded']);
            // If it wasn't cancelled yet (though it should be for eligibility), ENSURE it's deactivated
            if (! $refundable->canceled()) {
                $refundable->cancel(true);
            }
        } elseif ($refundable instanceof LabBooking) {
            // PRD Section 10: Refund Completion Logic
            $bookingService = app(\App\Services\Contracts\LabBookingServiceContract::class);
            
            // 1. Identify all refundable (non-attended) slots in the series
            $bookings = $refundable->bookingSeries
                ? $refundable->bookingSeries->bookings()->whereNotIn('status', [LabBooking::STATUS_COMPLETED, LabBooking::STATUS_CANCELLED])->get()
                : collect([$refundable]);

            foreach ($bookings as $b) {
                $wasQuota = $b->quota_consumed;
                
                // 2. Mark as Cancelled
                $b->update(['status' => LabBooking::STATUS_CANCELLED]);

                // 3. Restore Quota if applicable
                // Note: For Quota-only bookings, this was already handled in LabBookingService::cancelBooking.
                // For Mixed payments, we might still need to restore quota if it wasn't restored yet.
                // LabBookingService::releaseBookingQuota handles the checks itself.
                if ($wasQuota) {
                    $bookingService->releaseBookingQuota($b);
                }
            }

            if ($refundable->bookingSeries) {
                $refundable->bookingSeries->update(['status' => 'refunded']);
            }
        }

        // Update the payment status
        $payment = $refund->payment;
        if ($payment && $refund->amount >= $refund->original_amount) {
            // Full refund
            $payment->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refunded_by' => $refund->processed_by,
                'refund_reason' => $refund->reason,
            ]);

            // Notify user/guest
            if ($refund->refundable instanceof \App\Models\LabBooking) {
                $this->notifyPayer($refund, new \App\Notifications\LabBookingRefundProcessed($refund->refundable, $refund));
            } else {
                $this->notifyPayer($refund, new \App\Notifications\RefundProcessed($refund));
            }
        } elseif ($payment) {
            // Partial refund
            $payment->update([
                'status' => 'partially_refunded',
                'refunded_at' => now(), // Still set as "last refunded at" for analytics
                'refunded_by' => $refund->processed_by,
                'refund_reason' => $refund->reason,
                'meta' => array_merge($payment->meta ?? [], [
                    'refunded_amount' => ($payment->meta['refunded_amount'] ?? 0) + $refund->amount,
                ]),
            ]);
        }
    }

    /**
     * Notify the payer (User or Guest) of refund status updates.
     */
    protected function notifyPayer(Refund $refund, $notification): void
    {
        $refundable = $refund->refundable;
        if (! $refundable) {
            return;
        }

        // Try to get user first
        $user = null;
        if ($refundable instanceof \App\Models\User) {
            $user = $refundable;
        } elseif ($refundable instanceof \App\Models\EventOrder && $refundable->user) {
            $user = $refundable->user;
        } elseif ($refundable instanceof \App\Models\Donation && $refundable->user) {
            $user = $refundable->user;
        } elseif ($refundable instanceof \App\Models\LabBooking && $refundable->user) {
            $user = $refundable->user;
        } elseif ($refundable instanceof \App\Models\PlanSubscription && $refundable->user) {
            $user = $refundable->user;
        }

        if ($user) {
            $user->notify($notification);

            return;
        }

        // Handle guest/anonymous notification
        $email = null;
        if ($refundable instanceof EventOrder && $refundable->guest_email) {
            $email = $refundable->guest_email;
        } elseif ($refundable instanceof Donation && $refundable->donor_email) {
            $email = $refundable->donor_email;
        } elseif ($refundable instanceof LabBooking && $refundable->guest_email) {
            $email = $refundable->guest_email;
        }

        if ($email) {
            \Illuminate\Support\Facades\Notification::route('mail', $email)->notify($notification);
        }
    }

    /**
     * Notify staff members with manage_refunds permission.
     */
    protected function notifyStaff($notification): void
    {
        $staff = \App\Models\User::permission('manage_refunds')->get();
        \Illuminate\Support\Facades\Notification::send($staff, $notification);
    }

    /**
     * Initiate refund via payment gateway
     */
    protected function initiateGatewayRefund(Refund $refund): void
    {
        try {
            $payment = $refund->payment;
            if (! $payment) {
                throw new \Exception('No payment record associated with this refund.');
            }

            $reason = $refund->reason;
            if ($refund->customer_notes) {
                $reason .= ': '.$refund->customer_notes;
            }

            $result = $this->gatewayManager->refund(
                $payment->confirmation_code ?? $payment->transaction_id ?? $payment->external_reference,
                (float) $refund->amount,
                $reason
            );

            if ($result->success) {
                $this->completeRefund($refund);

                Log::info('Gateway refund successful', [
                    'refund_id' => $refund->id,
                    'transaction_id' => $result->transactionId,
                ]);
            } else {
                $refund->markFailed(['error' => $result->message ?? 'Gateway refund failed']);

                Log::warning('Gateway refund failed', [
                    'refund_id' => $refund->id,
                    'message' => $result->message,
                ]);
            }

        } catch (\Exception $e) {
            $refund->markFailed(['error' => $e->getMessage()]);
            Log::error('Gateway refund initiation failed', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle refund webhook from gateway
     */
    public function handleRefundWebhook(array $payload): void
    {
        $refundId = $payload['refund_reference'] ?? null;
        $status = $payload['status'] ?? null;

        if (! $refundId) {
            Log::warning('Refund webhook missing refund_reference', $payload);

            return;
        }

        $refund = Refund::where('gateway_refund_id', $refundId)->first();

        if (! $refund) {
            Log::warning('Refund not found for webhook', ['refund_id' => $refundId]);

            return;
        }

        if ($status === 'completed' || $status === 'success') {
            $this->completeRefund($refund);
        } else {
            $refund->markFailed($payload);
        }

        Log::info('Refund webhook processed', [
            'refund_id' => $refund->id,
            'status' => $status,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getStats(): array
    {
        return [
            'pending' => Refund::pending()->count(),
            'approved' => Refund::approved()->count(),
            'completed' => Refund::completed()->count(),
            'total_refunded' => (float) Refund::completed()->sum('amount'),
        ];
    }
}
