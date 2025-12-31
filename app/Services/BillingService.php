<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\EventOrder;
use App\Services\Contracts\BillingServiceContract;

/**
 * BillingService
 *
 * Implements business logic for billing operations and dashboard metrics.
 */
class BillingService implements BillingServiceContract
{
    /**
     * @inheritDoc
     */
    public function getDashboardSummary(): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        $donationStats = $this->getDonationStats();
        $eventStats = $this->getEventOrderStats();

        $last30DaysAmount = Donation::where('created_at', '>=', $thirtyDaysAgo)
            ->where('status', 'paid')
            ->sum('amount') +
            EventOrder::where('created_at', '>=', $thirtyDaysAgo)
                ->where('status', 'paid')
                ->sum('total_amount');

        return [
            'donations' => $donationStats,
            'event_orders' => $eventStats,
            'combined_total' => $donationStats['total_amount'] + $eventStats['total_revenue'],
            'combined_pending' => $donationStats['pending_amount'] + $eventStats['pending_revenue'],
            'last_30_days_total' => $last30DaysAmount,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getDonationStats(): array
    {
        return [
            'total' => Donation::count(),
            'paid' => Donation::where('status', 'paid')->count(),
            'pending' => Donation::where('status', 'pending')->count(),
            'failed' => Donation::where('status', 'failed')->count(),
            'total_amount' => (float) Donation::where('status', 'paid')->sum('amount'),
            'pending_amount' => (float) Donation::where('status', 'pending')->sum('amount'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getEventOrderStats(): array
    {
        return [
            'total' => EventOrder::count(),
            'paid' => EventOrder::where('status', 'paid')->count(),
            'pending' => EventOrder::where('status', 'pending')->count(),
            'failed' => EventOrder::where('status', 'failed')->count(),
            'total_revenue' => (float) EventOrder::where('status', 'paid')->sum('total_amount'),
            'pending_revenue' => (float) EventOrder::where('status', 'pending')->sum('total_amount'),
        ];
    }
}
