<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\EventOrder;
use App\Models\County;
use App\Services\DonationReconciliationService;
use App\Services\EventOrderReconciliationService;
use App\Services\BillingExportService;
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
    protected DonationReconciliationService $donationReconciliation;
    protected EventOrderReconciliationService $orderReconciliation;
    protected BillingExportService $exportService;

    public function __construct(
        DonationReconciliationService $donationReconciliation,
        EventOrderReconciliationService $orderReconciliation,
        BillingExportService $exportService,
    ) {
        $this->donationReconciliation = $donationReconciliation;
        $this->orderReconciliation = $orderReconciliation;
        $this->exportService = $exportService;
        
        // Require admin or finance role
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
     * Includes real-time totals for donations and event orders, broken down by payment status (paid, pending, failed).
     *
     * @group Admin - Billing Operations
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "donations": {
     *       "total": 250,
     *       "paid": 200,
     *       "pending": 40,
     *       "failed": 10,
     *       "total_amount": 50000.00,
     *       "pending_amount": 10000.00
     *     },
     *     "event_orders": {
     *       "total": 150,
     *       "paid": 130,
     *       "pending": 15,
     *       "failed": 5,
     *       "total_revenue": 75000.00,
     *       "pending_revenue": 5000.00
     *     },
     *     "combined_total": 125000.00,
     *     "combined_pending": 15000.00,
     *     "last_30_days_total": 25000.00
     *   }
     * }
     */
    public function getDashboardSummary(): JsonResponse
    {
        try {
            $thirtyDaysAgo = now()->subDays(30);

            $donations = [
                'total' => Donation::count(),
                'paid' => Donation::where('status', 'paid')->count(),
                'pending' => Donation::where('status', 'pending')->count(),
                'failed' => Donation::where('status', 'failed')->count(),
                'total_amount' => Donation::where('status', 'paid')->sum('amount'),
                'pending_amount' => Donation::where('status', 'pending')->sum('amount'),
            ];

            $eventOrders = [
                'total' => EventOrder::count(),
                'paid' => EventOrder::where('status', 'paid')->count(),
                'pending' => EventOrder::where('status', 'pending')->count(),
                'failed' => EventOrder::where('status', 'failed')->count(),
                'total_revenue' => EventOrder::where('status', 'paid')->sum('total_amount'),
                'pending_revenue' => EventOrder::where('status', 'pending')->sum('total_amount'),
            ];

            $last30Days = Donation::where('created_at', '>=', $thirtyDaysAgo)
                ->where('status', 'paid')
                ->sum('amount') +
                EventOrder::where('created_at', '>=', $thirtyDaysAgo)
                    ->where('status', 'paid')
                    ->sum('total_amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'donations' => $donations,
                    'event_orders' => $eventOrders,
                    'combined_total' => $donations['total_amount'] + $eventOrders['total_revenue'],
                    'combined_pending' => $donations['pending_amount'] + $eventOrders['pending_revenue'],
                    'last_30_days_total' => $last30Days,
                ],
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
     * Checks all pending donations against the payment gateway to identify paid but unrecorded transactions.
     *
     * @group Admin - Billing Operations
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "total_checked": 45,
     *     "reconciled": 40,
     *     "discrepancies": 5,
     *     "errors": []
     *   }
     * }
     */
    public function reconcileDonations(): JsonResponse
    {
        try {
            $results = $this->donationReconciliation->reconcileAll();

            Log::info('Donation reconciliation completed', $results);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Donation reconciliation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Reconciliation failed',
            ], 500);
        }
    }

    /**
     * Trigger Order Reconciliation
     *
     * Manually initiates the event order reconciliation job.
     * Verifies payment status for all pending event ticket orders.
     *
     * @group Admin - Billing Operations
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "total_checked": 30,
     *     "reconciled": 28,
     *     "discrepancies": 2,
     *     "errors": []
     *   }
     * }
     */
    public function reconcileOrders(): JsonResponse
    {
        try {
            $results = $this->orderReconciliation->reconcileAll();

            Log::info('Event order reconciliation completed', $results);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Event order reconciliation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Reconciliation failed',
            ], 500);
        }
    }

    /**
     * Get Reconciliation Status
     *
     * Retrieves the current count and details of unresolved discrepancies for both donations and event orders.
     *
     * @group Admin - Billing Operations
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "donation_discrepancies": {
     *       "missing_payments": [],
     *       "amount_mismatches": [],
     *       "status_mismatches": []
     *     },
     *     "order_discrepancies": {
     *       "missing_payments": [],
     *       "amount_mismatches": [],
     *       "quantity_issues": []
     *     }
     *   }
     * }
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reconciliation status',
            ], 500);
        }
    }

    /**
     * Export Donations CSV
     *
     * Downloads a CSV report of donations filtered by date range, county, or status.
     *
     * @group Admin - Billing Operations
     * @authenticated
     *
     * @queryParam start_date string optional Filter by start date (YYYY-MM-DD). Example: 2025-01-01
     * @queryParam end_date string optional Filter by end date (YYYY-MM-DD). Example: 2025-12-31
     * @queryParam county_id integer optional Filter by County ID. Example: 47
     * @queryParam status string optional Filter by status (paid, pending, failed, refunded). Example: paid
     *
     * @response 200 CSV file download
     */
    public function exportDonations(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d',
                'county_id' => 'nullable|integer|exists:counties,id',
                'status' => 'nullable|in:pending,paid,failed,refunded',
            ]);

            $csv = $this->exportService->exportDonations(
                $validated['start_date'] ? \Carbon\Carbon::createFromFormat('Y-m-d', $validated['start_date']) : null,
                $validated['end_date'] ? \Carbon\Carbon::createFromFormat('Y-m-d', $validated['end_date']) : null,
                $validated['county_id'] ?? null,
                $validated['status'] ?? null,
            );

            $filename = $this->exportService->generateFilename('donations');

            return response()->streamDownload(
                function() use ($csv) { echo $csv; },
                $filename,
                ['Content-Type' => 'text/csv']
            );
        } catch (\Exception $e) {
            Log::error('Donation export failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
            ], 500);
        }
    }

    /**
     * Export Event Orders CSV
     *
     * Downloads a CSV report of event ticket sales filtered by date range, event, or status.
     *
     * @group Admin - Billing Operations
     * @authenticated
     *
     * @queryParam start_date string optional Filter by start date (YYYY-MM-DD). Example: 2025-01-01
     * @queryParam end_date string optional Filter by end date (YYYY-MM-DD). Example: 2025-12-31
     * @queryParam event_id integer optional Filter by Event ID. Example: 5
     * @queryParam status string optional Filter by status (paid, pending, failed, refunded). Example: paid
     *
     * @response 200 CSV file download
     */
    public function exportEventOrders(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d',
                'event_id' => 'nullable|integer',
                'status' => 'nullable|in:pending,paid,failed,refunded',
            ]);

            $csv = $this->exportService->exportEventOrders(
                $validated['start_date'] ? \Carbon\Carbon::createFromFormat('Y-m-d', $validated['start_date']) : null,
                $validated['end_date'] ? \Carbon\Carbon::createFromFormat('Y-m-d', $validated['end_date']) : null,
                $validated['event_id'] ?? null,
                $validated['status'] ?? null,
            );

            $filename = $this->exportService->generateFilename('event_orders');

            return response()->streamDownload(
                function() use ($csv) { echo $csv; },
                $filename,
                ['Content-Type' => 'text/csv']
            );
        } catch (\Exception $e) {
            Log::error('Event order export failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
            ], 500);
        }
    }

    /**
     * Export Donation Summary (County)
     *
     * Downloads a summary CSV report aggregating donations by county for the specified period.
     *
     * @group Admin - Billing Operations
     * @authenticated
     *
     * @queryParam start_date string optional Filter by start date (YYYY-MM-DD). Example: 2025-01-01
     * @queryParam end_date string optional Filter by end date (YYYY-MM-DD). Example: 2025-12-31
     *
     * @response 200 CSV file download
     */
    public function exportDonationSummary(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d',
            ]);

            $csv = $this->exportService->exportDonationSummaryByCounty(
                $validated['start_date'] ? \Carbon\Carbon::createFromFormat('Y-m-d', $validated['start_date']) : null,
                $validated['end_date'] ? \Carbon\Carbon::createFromFormat('Y-m-d', $validated['end_date']) : null,
            );

            $filename = $this->exportService->generateFilename('donation_summary_by_county');

            return response()->streamDownload(
                function() use ($csv) { echo $csv; },
                $filename,
                ['Content-Type' => 'text/csv']
            );
        } catch (\Exception $e) {
            Log::error('Donation summary export failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
            ], 500);
        }
    }

    /**
     * Export Event Sales Summary
     *
     * Downloads a summary CSV report aggregating ticket sales data.
     *
     * @group Admin - Billing Operations
     * @authenticated
     *
     * @queryParam start_date string optional Filter by start date (YYYY-MM-DD). Example: 2025-01-01
     * @queryParam end_date string optional Filter by end date (YYYY-MM-DD). Example: 2025-12-31
     *
     * @response 200 CSV file download
     */
    public function exportEventSalesSummary(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d',
            ]);

            $csv = $this->exportService->exportEventSalesSummary(
                $validated['start_date'] ? \Carbon\Carbon::createFromFormat('Y-m-d', $validated['start_date']) : null,
                $validated['end_date'] ? \Carbon\Carbon::createFromFormat('Y-m-d', $validated['end_date']) : null,
            );

            $filename = $this->exportService->generateFilename('event_sales_summary');

            return response()->streamDownload(
                function() use ($csv) { echo $csv; },
                $filename,
                ['Content-Type' => 'text/csv']
            );
        } catch (\Exception $e) {
            Log::error('Event sales summary export failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
            ], 500);
        }
    }

    /**
     * Export Financial Reconciliation
     *
     * Downloads the detailed financial reconciliation report, matching internal records against potential payment gateway entries.
     *
     * @group Admin - Billing Operations
     * @authenticated
     *
     * @queryParam start_date string optional Filter by start date (YYYY-MM-DD). Example: 2025-01-01
     * @queryParam end_date string optional Filter by end date (YYYY-MM-DD). Example: 2025-12-31
     *
     * @response 200 CSV file download
     */
    public function exportFinancialReconciliation(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d',
            ]);

            $csv = $this->exportService->exportFinancialReconciliation(
                $validated['start_date'] ? \Carbon\Carbon::createFromFormat('Y-m-d', $validated['start_date']) : null,
                $validated['end_date'] ? \Carbon\Carbon::createFromFormat('Y-m-d', $validated['end_date']) : null,
            );

            $filename = $this->exportService->generateFilename('financial_reconciliation');

            return response()->streamDownload(
                function() use ($csv) { echo $csv; },
                $filename,
                ['Content-Type' => 'text/csv']
            );
        } catch (\Exception $e) {
            Log::error('Financial reconciliation export failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
            ], 500);
        }
    }
}
