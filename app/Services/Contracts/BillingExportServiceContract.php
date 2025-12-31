<?php

namespace App\Services\Contracts;

/**
 * BillingExportServiceContract
 *
 * Defines contract for financial and billing data exports.
 */
interface BillingExportServiceContract
{
    /**
     * Export donations to CSV
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @param int|null $countyId
     * @param string|null $status
     * @return string
     */
    public function exportDonations($startDate = null, $endDate = null, $countyId = null, $status = null): string;

    /**
     * Export event orders to CSV
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @param int|null $eventId
     * @param string|null $status
     * @return string
     */
    public function exportEventOrders($startDate = null, $endDate = null, $eventId = null, $status = null): string;

    /**
     * Export donation summary by county
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @return string
     */
    public function exportDonationSummaryByCounty($startDate = null, $endDate = null): string;

    /**
     * Export event sales summary
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @return string
     */
    public function exportEventSalesSummary($startDate = null, $endDate = null): string;

    /**
     * Export financial reconciliation report
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @return string
     */
    public function exportFinancialReconciliation($startDate = null, $endDate = null): string;

    /**
     * Generate filename with timestamp
     *
     * @param string $type
     * @return string
     */
    public function generateFilename(string $type): string;
}
