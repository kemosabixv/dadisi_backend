<?php

namespace App\Services\Contracts;

/**
 * BillingServiceContract
 *
 * Defines contract for billing dashboard and financial aggregation.
 */
interface BillingServiceContract
{
    /**
     * Get billing dashboard summary
     *
     * @return array
     */
    public function getDashboardSummary(): array;

    /**
     * Get financial statistics for donations
     *
     * @return array
     */
    public function getDonationStats(): array;

    /**
     * Get financial statistics for event orders
     *
     * @return array
     */
    public function getEventOrderStats(): array;
}
