<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Reconciliation\CsvExporter;

/**
 * @group Admin - Reconciliation Reports
 * @groupDescription Dedicated endpoint for exporting detailed financial reconciliation reports matching internal records against gateway statements.
 */
class ReconciliationController extends Controller
{
    public function __construct()
    {
        // Policy or middleware should restrict this to admins/super-admins
        $this->middleware('auth:sanctum');
    }

    /**
     * Export Reconciliation Data (CSV)
     *
     * Generates a detailed CSV export of transactions for financial reconciliation.
     * This report allows finance teams to match internal system records against payment gateway statements (e.g., Pesapal, MPESA).
     * Includes transaction references, amounts, dates, and current reconciliation status.
     *
     * @group Admin - Reconciliation Reports
     * @groupDescription Dedicated endpoint for exporting detailed financial reconciliation reports matching internal records against gateway statements.
     * @authenticated
     *
     * @queryParam start_date string optional Start date for reconciliation period (ISO 8601). Example: 2025-01-01
     * @queryParam end_date string optional End date for reconciliation period (ISO 8601). Example: 2025-12-31
     * @queryParam county string optional Filter by county name. Example: Nairobi
     * @queryParam status string optional Filter by transaction status (matched, unmatched_app, unmatched_gateway). Example: unmatched_gateway
     *
     * @response 200 {
     *   "headers": ["run_id", "transaction_id", "reference", "source", "date", "amount", "status"],
     *   "rows": [
     *     [1, "tx_1", "ref_1", "app", "2025-12-12T10:30:00Z", "100.00", "matched"],
     *     [1, "tx_2", "ref_2", "gateway", "2025-12-12T10:35:00Z", "50.00", "unmatched_gateway"]
     *   ],
     *   "file": "reconciliation_sample.csv"
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function export(Request $request)
    {
        // Validate query parameters
        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'county' => 'nullable|string|max:100',
            'status' => 'nullable|in:matched,unmatched_app,unmatched_gateway',
        ]);

        // Simple stub: produce a tiny CSV for demo/testing. Real implementation
        // will query reconciliation items and build rows.
        $headers = ['run_id', 'transaction_id', 'reference', 'source', 'date', 'amount', 'status'];
        $rows = [
            [1, 'tx_1', 'ref_1', 'app', now()->toIso8601String(), '100.00', 'matched'],
            [1, 'tx_2', 'ref_2', 'gateway', now()->toIso8601String(), '50.00', 'unmatched_gateway'],
        ];

        return CsvExporter::streamDownloadResponse('reconciliation_sample.csv', $headers, $rows);
    }
}
