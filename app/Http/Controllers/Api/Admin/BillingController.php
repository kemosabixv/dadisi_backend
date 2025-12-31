<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Contracts\BillingServiceContract;
use App\Services\Contracts\BillingExportServiceContract;
use App\Services\Reconciliation\DonationReconciliationService;
use App\Services\Reconciliation\EventOrderReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin Billing Controller
 *
 * Handles billing operations, reconciliation, and exports for finance/admin users.
 *
 * @group Admin - Billing Operations
 * @groupDescription Endpoints for billing dashboard aggregation, manual reconciliation triggers (donations, event orders), and financial data exports (CSV).
 * @authenticated
 */
class BillingController extends Controller
{
    public function __construct(
        private BillingServiceContract $billingService,
        private DonationReconciliationService $donationReconciliation,
        private EventOrderReconciliationService $orderReconciliation,
        private BillingExportServiceContract $exportService,
    ) {
        $this->middleware(['auth:sanctum', 'admin']);
        
        // Additional requirement for finance role for sensitive operations
        $this->middleware('permission:manage-finances')->only([
            'reconcileDonations',
            'reconcileOrders',
            'getReconciliationStatus',
            'exportDonations',
            'exportEventOrders',
            'exportDonationSummary',
            'exportEventSalesSummary',
            'exportFinancialReconciliation',
        ]);
    }

    /**
     * Get Billing Dashboard Stats
     *
     * Retrieves aggregated financial metrics for the admin dashboard.
     * Includes real-time totals for donations and event orders, broken down by payment status.
     *
     * @authenticated
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "donations": {...},
     *     "event_orders": {...},
     *     "combined_total": 125000.00,
     *     "combined_pending": 15000.00,
     *     "last_30_days_total": 25000.00
     *   }
     * }
     */
    public function getDashboardSummary(): JsonResponse
    {
        try {
            $summary = $this->billingService->getDashboardSummary();

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get billing summary', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve billing summary',
            ], 500);
        }
    }

    /**
     * Trigger Donation Reconciliation
     *
     * Manually initiates the donation reconciliation job.
     */
    public function reconcileDonations(): JsonResponse
    {
        try {
            $results = $this->donationReconciliation->reconcileAll();
            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Donation reconciliation failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Reconciliation failed'], 500);
        }
    }

    /**
     * Trigger Order Reconciliation
     *
     * Manually initiates the event order reconciliation job.
     */
    public function reconcileOrders(): JsonResponse
    {
        try {
            $results = $this->orderReconciliation->reconcileAll();
            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Event order reconciliation failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Reconciliation failed'], 500);
        }
    }

    /**
     * Get Reconciliation Status
     *
     * Retrieves current unresolved discrepancies.
     */
    public function getReconciliationStatus(): JsonResponse
    {
        try {
            $donationDiscrepancies = $this->donationReconciliation->detectDiscrepancies();
            $orderDiscrepancies = $this->orderReconciliation->detectDiscrepancies();

            return response()->json([
                'success' => true,
                'data' => [
                    'donation_discrepancies' => $donationDiscrepancies,
                    'order_discrepancies' => $orderDiscrepancies,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get reconciliation status', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve status'], 500);
        }
    }

    /**
     * Export Donations CSV
     */
    public function exportDonations(Request $request)
    {
        try {
            $csv = $this->exportService->exportDonations(
                $request->input('start_date'),
                $request->input('end_date'),
                $request->input('county_id'),
                $request->input('status')
            );

            $filename = $this->exportService->generateFilename('donations');

            return response()->streamDownload(
                function() use ($csv) { echo $csv; },
                $filename,
                ['Content-Type' => 'text/csv']
            );
        } catch (\Exception $e) {
            Log::error('Donation export failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Export failed'], 500);
        }
    }

    /**
     * Export Event Orders CSV
     */
    public function exportEventOrders(Request $request)
    {
        try {
            $csv = $this->exportService->exportEventOrders(
                $request->input('start_date'),
                $request->input('end_date'),
                $request->input('event_id'),
                $request->input('status')
            );

            $filename = $this->exportService->generateFilename('event_orders');

            return response()->streamDownload(
                function() use ($csv) { echo $csv; },
                $filename,
                ['Content-Type' => 'text/csv']
            );
        } catch (\Exception $e) {
            Log::error('Event order export failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Export failed'], 500);
        }
    }

    /**
     * Export Donation Summary (County)
     */
    public function exportDonationSummary(Request $request)
    {
        try {
            $csv = $this->exportService->exportDonationSummaryByCounty(
                $request->input('start_date'),
                $request->input('end_date')
            );

            $filename = $this->exportService->generateFilename('donation_summary_by_county');

            return response()->streamDownload(
                function() use ($csv) { echo $csv; },
                $filename,
                ['Content-Type' => 'text/csv']
            );
        } catch (\Exception $e) {
            Log::error('Donation summary export failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Export failed'], 500);
        }
    }

    /**
     * Export Event Sales Summary
     */
    public function exportEventSalesSummary(Request $request)
    {
        try {
            $csv = $this->exportService->exportEventSalesSummary(
                $request->input('start_date'),
                $request->input('end_date')
            );

            $filename = $this->exportService->generateFilename('event_sales_summary');

            return response()->streamDownload(
                function() use ($csv) { echo $csv; },
                $filename,
                ['Content-Type' => 'text/csv']
            );
        } catch (\Exception $e) {
            Log::error('Event sales summary export failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Export failed'], 500);
        }
    }

    /**
     * Export Financial Reconciliation
     */
    public function exportFinancialReconciliation(Request $request)
    {
        try {
            $csv = $this->exportService->exportFinancialReconciliation(
                $request->input('start_date'),
                $request->input('end_date')
            );

            $filename = $this->exportService->generateFilename('financial_reconciliation');

            return response()->streamDownload(
                function() use ($csv) { echo $csv; },
                $filename,
                ['Content-Type' => 'text/csv']
            );
        } catch (\Exception $e) {
            Log::error('Financial reconciliation export failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Export failed'], 500);
        }
    }
}
