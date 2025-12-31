<?php

namespace App\Services\Reconciliation;

use App\Models\EventOrder;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EventOrderReconciliationService
 *
 * Handles reconciliation of event orders with payment records.
 * Ensures consistency and detects payment discrepancies.
 */
class EventOrderReconciliationService
{
    /**
     * Reconcile all pending orders with payment status
     */
    public function reconcileAll(): array
    {
        $results = [
            'total_checked' => 0,
            'reconciled' => 0,
            'discrepancies' => 0,
            'errors' => [],
        ];

        try {
            $pendingOrders = EventOrder::where('status', 'pending')
                ->with('payment')
                ->get();

            $results['total_checked'] = count($pendingOrders);

            foreach ($pendingOrders as $order) {
                try {
                    $this->reconcileOrder($order);
                    $results['reconciled']++;
                } catch (\Exception $e) {
                    $results['discrepancies']++;
                    $results['errors'][] = [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Event order reconciliation error', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Batch event order reconciliation failed', [
                'error' => $e->getMessage(),
            ]);
            $results['errors'][] = [
                'batch' => true,
                'error' => $e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * Reconcile a single order
     */
    public function reconcileOrder(EventOrder $order): bool
    {
        DB::beginTransaction();

        try {
            // Check if payment exists
            if ($order->payment) {
                $payment = $order->payment;

                // Check if payment amount matches order amount
                if ($payment->amount != $order->total_amount) {
                    Log::warning('Event order amount mismatch', [
                        'order_id' => $order->id,
                        'order_amount' => $order->total_amount,
                        'payment_amount' => $payment->amount,
                    ]);
                }

                // Update order status from payment status
                if ($payment->isPaid()) {
                    $order->status = 'paid';
                    $order->save();
                } elseif ($payment->status === 'failed') {
                    $order->status = 'failed';
                    $order->save();
                } elseif ($payment->status === 'refunded') {
                    $order->status = 'refunded';
                    $order->save();
                }
            } else {
                // No payment associated - check if webhook event exists
                $webhookEvent = WebhookEvent::where('order_reference', $order->reference)->first();

                if ($webhookEvent && $webhookEvent->status === 'processed') {
                    // Webhook confirmed payment, create payment record
                    $this->createPaymentFromWebhookEvent($order, $webhookEvent);
                }
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create payment record from webhook event
     */
    protected function createPaymentFromWebhookEvent(EventOrder $order, WebhookEvent $webhookEvent): void
    {
        $paymentData = $webhookEvent->payload ?? [];

        Payment::create([
            'payable_type' => 'event_order',
            'payable_id' => $order->id,
            'gateway' => 'pesapal',
            'method' => $paymentData['payment_method'] ?? null,
            'status' => 'paid',
            'amount' => $order->total_amount,
            'currency' => $order->currency,
            'external_reference' => $webhookEvent->external_id,
            'order_reference' => $webhookEvent->order_reference,
            'receipt_url' => $paymentData['receipt_url'] ?? null,
            'paid_at' => $webhookEvent->processed_at,
            'meta' => $paymentData,
        ]);

        $order->status = 'paid';
        $order->save();
    }

    /**
     * Get reconciliation summary
     */
    public function getSummary(): array
    {
        return [
            'total_orders' => EventOrder::count(),
            'paid_orders' => EventOrder::where('status', 'paid')->count(),
            'pending_orders' => EventOrder::where('status', 'pending')->count(),
            'failed_orders' => EventOrder::where('status', 'failed')->count(),
            'total_revenue' => EventOrder::where('status', 'paid')->sum('total_amount'),
            'pending_revenue' => EventOrder::where('status', 'pending')->sum('total_amount'),
        ];
    }

    /**
     * Get summary by event
     */
    public function getSummaryByEvent()
    {
        return DB::table('event_orders')
            ->select(
                'events.id',
                'events.title',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(CASE WHEN event_orders.status = "paid" THEN 1 ELSE 0 END) as paid_orders'),
                DB::raw('SUM(CASE WHEN event_orders.status = "pending" THEN 1 ELSE 0 END) as pending_orders'),
                DB::raw('SUM(event_orders.quantity) as total_tickets'),
                DB::raw('SUM(CASE WHEN event_orders.status = "paid" THEN event_orders.total_amount ELSE 0 END) as total_revenue'),
            )
            ->join('events', 'event_orders.event_id', '=', 'events.id')
            ->groupBy('events.id', 'events.title')
            ->orderBy('total_revenue', 'desc')
            ->get();
    }

    /**
     * Detect discrepancies between orders and payments
     */
    public function detectDiscrepancies(): array
    {
        $discrepancies = [
            'missing_payments' => [],
            'amount_mismatches' => [],
            'quantity_issues' => [],
        ];

        // Find orders with no payment
        $missingPayments = EventOrder::where('status', 'paid')
            ->whereNull('payment_id')
            ->get();

        foreach ($missingPayments as $order) {
            $discrepancies['missing_payments'][] = [
                'order_id' => $order->id,
                'reference' => $order->reference,
                'amount' => $order->total_amount,
            ];
        }

        // Find amount mismatches
        $mismatches = DB::table('event_orders')
            ->join('payments', function ($join) {
                $join->on('event_orders.payment_id', '=', 'payments.id')
                    ->where('payments.payable_type', '=', 'event_order');
            })
            ->where('event_orders.total_amount', '!=', 'payments.amount')
            ->select('event_orders.id', 'event_orders.reference', 'event_orders.total_amount as order_amount', 'payments.amount as payment_amount')
            ->get();

        foreach ($mismatches as $mismatch) {
            $discrepancies['amount_mismatches'][] = (array)$mismatch;
        }

        return $discrepancies;
    }
}
