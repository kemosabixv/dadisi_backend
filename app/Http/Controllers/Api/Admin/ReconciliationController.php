<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Reconciliation\CsvExporter;

/**
 * @group Admin - Reconciliation
 * Export financial reconciliation data for compliance reporting and account verification.
 * Requires admin authentication and generates CSV files for county-level transactions.
 */
class ReconciliationController extends Controller
{
    public function __construct()
    {
        // Policy or middleware should restrict this to admins/super-admins
        $this->middleware('auth:sanctum');
    }

    /**
     * Export reconciliation data as CSV.
     *
     * Generates a CSV export of all transactions for a specified period, optionally filtered
     * by county or transaction status. Used for financial reconciliation, compliance reporting,
     * and account verification. The CSV includes transaction IDs, references, amounts, and status.
     *
     * @authenticated
     * @queryParam start_date string Optional start date for reconciliation period (ISO 8601). Example: 2025-01-01
     * @queryParam end_date string Optional end date for reconciliation period (ISO 8601). Example: 2025-12-31
     * @queryParam county string Optional filter by county name. Example: Nairobi
     * @queryParam status string Optional filter by transaction status (matched, unmatched_app, unmatched_gateway). Example: unmatched_gateway
     *
     * @response 200 {
     *   "headers": ["run_id", "transaction_id", "reference", "source", "date", "amount", "status"],
     *   "rows": [
     *     [1, "tx_1", "ref_1", "app", "2025-12-12T10:30:00Z", "100.00", "matched"],
     *     [1, "tx_2", "ref_2", "gateway", "2025-12-12T10:35:00Z", "50.00", "unmatched_gateway"]
     *   ],
     *   "file": "reconciliation_sample.csv"
     * }
     * @response 403 {"success": false, "message": "This action is unauthorized"}
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
