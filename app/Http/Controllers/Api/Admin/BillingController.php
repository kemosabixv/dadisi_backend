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
 * @group Admin - Billing
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
     * Get billing dashboard summary
     *
     * @description Get high-level billing metrics and status
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
     * Reconcile all pending donations
     *
     * @description Run reconciliation job for all pending donations
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
     * Reconcile all pending event orders
     *
     * @description Run reconciliation job for all pending event orders
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
     * Get reconciliation status
     *
     * @description Get current reconciliation status including discrepancies
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
     * Export donations as CSV
     *
     * @queryParam start_date string Date in format YYYY-MM-DD (optional)
     * @queryParam end_date string Date in format YYYY-MM-DD (optional)
     * @queryParam county_id integer County ID filter (optional)
     * @queryParam status string Payment status: paid|pending|failed|refunded (optional)
     *
     * @response 200 CSV file
     */
    public function exportDonations(Request $request): StreamedResponse
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
     * Export event orders as CSV
     *
     * @queryParam start_date string Date in format YYYY-MM-DD (optional)
     * @queryParam end_date string Date in format YYYY-MM-DD (optional)
     * @queryParam event_id integer Event ID filter (optional)
     * @queryParam status string Payment status: paid|pending|failed|refunded (optional)
     *
     * @response 200 CSV file
     */
    public function exportEventOrders(Request $request): StreamedResponse
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
     * Export donation summary by county
     *
     * @queryParam start_date string Date in format YYYY-MM-DD (optional)
     * @queryParam end_date string Date in format YYYY-MM-DD (optional)
     *
     * @response 200 CSV file
     */
    public function exportDonationSummary(Request $request): StreamedResponse
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
     * Export event sales summary
     *
     * @queryParam start_date string Date in format YYYY-MM-DD (optional)
     * @queryParam end_date string Date in format YYYY-MM-DD (optional)
     *
     * @response 200 CSV file
     */
    public function exportEventSalesSummary(Request $request): StreamedResponse
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
     * Export financial reconciliation report
     *
     * @queryParam start_date string Date in format YYYY-MM-DD (optional)
     * @queryParam end_date string Date in format YYYY-MM-DD (optional)
     *
     * @response 200 CSV file
     */
    public function exportFinancialReconciliation(Request $request): StreamedResponse
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
