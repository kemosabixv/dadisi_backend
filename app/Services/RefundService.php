<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\EventOrder;
use App\Models\Payment;
use App\Models\Refund;
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
        private \App\Services\PaymentGateway\GatewayManager $gatewayManager
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
    public function requestEventOrderRefund(
        EventOrder $order,
        string $reason,
        ?string $customerNotes = null,
        ?float $amount = null
    ): Refund {
        // Validate order can be refunded
        if (! $order->isPaid()) {
            throw new \Exception('Only paid orders can be refunded.');
        }

        // Check if there's already a pending refund
        $existingRefund = Refund::where('refundable_type', EventOrder::class)
            ->where('refundable_id', $order->id)
            ->whereIn('status', [Refund::STATUS_PENDING, Refund::STATUS_APPROVED, Refund::STATUS_PROCESSING])
            ->first();

        if ($existingRefund) {
            throw new \Exception('A refund is already pending for this order.');
        }

        // Get the payment
        $payment = $order->payment;
        if (! $payment) {
            throw new \Exception('No payment found for this order.');
        }

        // Calculate refund amount (default to full refund)
        $refundAmount = $amount ?? $order->total_amount;

        // Validate amount doesn't exceed original
        if ($refundAmount > $order->total_amount) {
            throw new \Exception('Refund amount cannot exceed the original payment amount.');
        }

        return Refund::create([
            'refundable_type' => EventOrder::class,
            'refundable_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => $refundAmount,
            'currency' => $order->currency,
            'original_amount' => $order->total_amount,
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
        // Validate donation can be refunded
        if ($donation->status !== 'paid') {
            throw new \Exception('Only completed donations can be refunded.');
        }

        // Check if there's already a pending refund
        $existingRefund = Refund::where('refundable_type', Donation::class)
            ->where('refundable_id', $donation->id)
            ->whereIn('status', [Refund::STATUS_PENDING, Refund::STATUS_APPROVED, Refund::STATUS_PROCESSING])
            ->first();

        if ($existingRefund) {
            throw new \Exception('A refund is already pending for this donation.');
        }

        // Get the payment
        $payment = $donation->payment;
        if (! $payment) {
            throw new \Exception('No payment found for this donation.');
        }

        return Refund::create([
            'refundable_type' => Donation::class,
            'refundable_id' => $donation->id,
            'payment_id' => $payment->id,
            'amount' => $donation->amount,
            'currency' => $donation->currency,
            'original_amount' => $donation->amount,
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
    public function approveRefund(Refund $refund, Authenticatable $admin, ?string $adminNotes = null): Refund
    {
        if (! $refund->isPending()) {
            throw new \Exception('Only pending refunds can be approved.');
        }

        $refund->approve($admin);

        if ($adminNotes) {
            $refund->update(['admin_notes' => $adminNotes]);
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
    public function rejectRefund(Refund $refund, Authenticatable $admin, string $reason): Refund
    {
        if (! $refund->isPending()) {
            throw new \Exception('Only pending refunds can be rejected.');
        }

        $refund->reject($admin, $reason);

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

            // For mock/local environment, auto-complete the refund
            if (in_array(app()->environment(), ['local', 'testing', 'staging']) ||
                $refund->gateway === 'mock') {

                $this->completeRefund($refund);

            } else {
                // For real gateways (Pesapal), initiate refund via gateway
                // This would call the payment gateway's refund API
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
        } elseif ($refundable instanceof Donation) {
            $refundable->update(['status' => 'refunded']);
        }

        // Update the payment status
        $payment = $refund->payment;
        if ($payment && $refund->amount >= $refund->original_amount) {
            // Full refund
            $payment->update(['status' => 'refunded']);

            // Notify user
            if ($payment->payer) {
                try {
                    $payment->payer->notify(new \App\Notifications\RefundProcessed($refund));
                } catch (\Exception $e) {
                    Log::error('Failed to send refund notification', [
                        'refund_id' => $refund->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } elseif ($payment) {
            // Partial refund
            $payment->update([
                'status' => 'partially_refunded',
                'meta' => array_merge($payment->meta ?? [], [
                    'refunded_amount' => ($payment->meta['refunded_amount'] ?? 0) + $refund->amount,
                ]),
            ]);
        }
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
                $payment->transaction_id ?? $payment->external_reference,
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
            'total_refunded' => Refund::completed()->sum('amount'),
        ];
    }
}
