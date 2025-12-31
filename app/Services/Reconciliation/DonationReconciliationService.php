<?php

namespace App\Services\Reconciliation;

use App\Models\Donation;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DonationReconciliationService
 *
 * Handles reconciliation of donations with payment records and webhook events.
 * Ensures data consistency and detects discrepancies.
 */
class DonationReconciliationService
{
    /**
     * Reconcile all pending donations with payment status
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
            $pendingDonations = Donation::where('status', 'pending')
                ->with('payment')
                ->get();

            $results['total_checked'] = count($pendingDonations);

            foreach ($pendingDonations as $donation) {
                try {
                    $this->reconcileDonation($donation);
                    $results['reconciled']++;
                } catch (\Exception $e) {
                    $results['discrepancies']++;
                    $results['errors'][] = [
                        'donation_id' => $donation->id,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Donation reconciliation error', [
                        'donation_id' => $donation->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Batch donation reconciliation failed', [
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
     * Reconcile a single donation
     */
    public function reconcileDonation(Donation $donation): bool
    {
        DB::beginTransaction();

        try {
            // Check if payment exists
            if ($donation->payment) {
                $payment = $donation->payment;

                // Check if payment amount matches donation amount
                if ($payment->amount != $donation->amount) {
                    Log::warning('Donation amount mismatch', [
                        'donation_id' => $donation->id,
                        'donation_amount' => $donation->amount,
                        'payment_amount' => $payment->amount,
                    ]);
                }

                // Update donation status from payment status
                if ($payment->isPaid()) {
                    $donation->status = 'paid';
                    $donation->save();
                } elseif ($payment->status === 'failed') {
                    $donation->status = 'failed';
                    $donation->save();
                } elseif ($payment->status === 'refunded') {
                    $donation->status = 'refunded';
                    $donation->save();
                }
            } else {
                // No payment associated - check if webhook event exists
                $webhookEvent = WebhookEvent::where('order_reference', $donation->reference)->first();

                if ($webhookEvent && $webhookEvent->status === 'processed') {
                    // Webhook confirmed payment, create payment record
                    $this->createPaymentFromWebhookEvent($donation, $webhookEvent);
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
    protected function createPaymentFromWebhookEvent(Donation $donation, WebhookEvent $webhookEvent): void
    {
        $paymentData = $webhookEvent->payload ?? [];

        Payment::create([
            'payable_type' => 'donation',
            'payable_id' => $donation->id,
            'gateway' => 'pesapal',
            'method' => $paymentData['payment_method'] ?? null,
            'status' => 'paid',
            'amount' => $donation->amount,
            'currency' => $donation->currency,
            'external_reference' => $webhookEvent->external_id,
            'order_reference' => $webhookEvent->order_reference,
            'receipt_url' => $paymentData['receipt_url'] ?? null,
            'paid_at' => $webhookEvent->processed_at,
            'meta' => $paymentData,
        ]);

        $donation->status = 'paid';
        $donation->save();
    }

    /**
     * Get reconciliation summary by county
     */
    public function getSummaryByCounty(): Collection
    {
        return DB::table('donations')
            ->select(
                'counties.id',
                'counties.name',
                DB::raw('COUNT(*) as total_donations'),
                DB::raw('SUM(CASE WHEN donations.status = "paid" THEN 1 ELSE 0 END) as paid_donations'),
                DB::raw('SUM(CASE WHEN donations.status = "pending" THEN 1 ELSE 0 END) as pending_donations'),
                DB::raw('SUM(CASE WHEN donations.status = "failed" THEN 1 ELSE 0 END) as failed_donations'),
                DB::raw('SUM(CASE WHEN donations.status = "paid" THEN donations.amount ELSE 0 END) as total_paid'),
                DB::raw('SUM(CASE WHEN donations.status = "pending" THEN donations.amount ELSE 0 END) as total_pending'),
            )
            ->join('counties', 'donations.county_id', '=', 'counties.id')
            ->groupBy('counties.id', 'counties.name')
            ->orderBy('total_paid', 'desc')
            ->get();
    }

    /**
     * Get reconciliation summary by date range
     */
    public function getSummaryByDateRange($startDate, $endDate): array
    {
        return DB::table('donations')
            ->select(
                DB::raw('DATE(donations.created_at) as date'),
                DB::raw('COUNT(*) as total_donations'),
                DB::raw('SUM(CASE WHEN donations.status = "paid" THEN 1 ELSE 0 END) as paid_donations'),
                DB::raw('SUM(CASE WHEN donations.status = "paid" THEN donations.amount ELSE 0 END) as total_paid'),
            )
            ->whereBetween('donations.created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(donations.created_at)'))
            ->orderBy('date', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Detect discrepancies between donations and payments
     */
    public function detectDiscrepancies(): array
    {
        $discrepancies = [
            'missing_payments' => [],
            'amount_mismatches' => [],
            'status_mismatches' => [],
        ];

        // Find donations with no payment
        $missingPayments = Donation::where('status', 'paid')
            ->whereNull('payment_id')
            ->get();

        foreach ($missingPayments as $donation) {
            $discrepancies['missing_payments'][] = [
                'donation_id' => $donation->id,
                'reference' => $donation->reference,
                'amount' => $donation->amount,
            ];
        }

        // Find amount mismatches
        $mismatches = DB::table('donations')
            ->join('payments', function ($join) {
                $join->on('donations.payment_id', '=', 'payments.id')
                    ->where('payments.payable_type', '=', 'donation');
            })
            ->where('donations.amount', '!=', 'payments.amount')
            ->select('donations.id', 'donations.reference', 'donations.amount as donation_amount', 'payments.amount as payment_amount')
            ->get();

        foreach ($mismatches as $mismatch) {
            $discrepancies['amount_mismatches'][] = (array)$mismatch;
        }

        return $discrepancies;
    }
}
