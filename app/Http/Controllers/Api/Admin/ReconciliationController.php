<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TriggerReconciliationRequest;
use App\Http\Requests\Api\ExportReconciliationRequest;
use App\Models\ReconciliationRun;
use App\Models\ReconciliationItem;
use App\Services\Reconciliation\ReconciliationService;
use App\Services\Reconciliation\CsvExporter;
use App\Jobs\RunReconciliationJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Admin - Reconciliation
 * Manage reconciliation runs, match transactions, and export discrepancy reports.
 * All endpoints require Sanctum authentication and appropriate permissions (admin/finance roles).
 */
class ReconciliationController extends Controller
{
    protected ReconciliationService $service;
    protected CsvExporter $exporter;

    public function __construct(ReconciliationService $service, CsvExporter $exporter)
    {
        $this->service = $service;
        $this->exporter = $exporter;

        // Require Sanctum authentication - authorization handled via policies
        $this->middleware('auth:sanctum');
    }

    /**
     * List all reconciliation runs with pagination and filters.
     *
     * Retrieves paginated list of reconciliation runs with optional filtering by status,
     * date range, and county. Returns paginated data with summary statistics per run.
     * Results are sorted by most recent first.
     *
     * @authenticated
     * @queryParam status string Filter by run status (success, failed, pending). Example: success
     * @queryParam period_start date Filter runs started from this date (YYYY-MM-DD). Example: 2025-01-01
     * @queryParam period_end date Filter runs started until this date (YYYY-MM-DD). Example: 2025-12-31
     * @queryParam county string Filter by county. Example: Nairobi
     * @queryParam per_page integer Items per page. Default: 15. Example: 20
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "run_id": "RUN-2025-001",
     *       "status": "success",
     *       "county": "Nairobi",
     *       "started_at": "2025-01-15T10:30:00Z",
     *       "completed_at": "2025-01-15T10:45:00Z",
     *       "total_matched": 145,
     *       "total_unmatched_app": 3,
     *       "total_unmatched_gateway": 2
     *     }
     *   ],
     *   "pagination": {
     *     "total": 45,
     *     "per_page": 15,
     *     "current_page": 1,
     *     "last_page": 3
     *   }
     * }
     * @response 403 {"message": "This action is unauthorized"}
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ReconciliationRun::class);

        $query = ReconciliationRun::query();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by date range
        if ($request->filled('period_start')) {
            $query->whereDate('started_at', '>=', $request->input('period_start'));
        }
        if ($request->filled('period_end')) {
            $query->whereDate('started_at', '<=', $request->input('period_end'));
        }

        // Filter by county
        if ($request->filled('county')) {
            $query->where('county', $request->input('county'));
        }

        // Sort by latest first
        $runs = $query->orderByDesc('started_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $runs->items(),
            'pagination' => [
                'total' => $runs->total(),
                'per_page' => $runs->perPage(),
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
            ],
        ]);
    }

    /**
     * Show details of a specific reconciliation run.
     *
     * Retrieves detailed information about a single reconciliation run including all matched/unmatched
     * transactions and comprehensive summary metrics (totals, mismatches, discrepancies).
     * Use this to inspect individual run results and view linked transaction pairs.
     *
     * @authenticated
     * @response 200 {
     *   "run": {
     *     "id": 1,
     *     "run_id": "RUN-2025-001",
     *     "status": "success",
     *     "county": "Nairobi",
     *     "started_at": "2025-01-15T10:30:00Z",
     *     "completed_at": "2025-01-15T10:45:00Z",
     *     "created_by": 5,
     *     "items": [
     *       {
     *         "id": 101,
     *         "transaction_id": "APP-12345",
     *         "reference": "PESAPAL-REF-001",
     *         "amount": 5000.00,
     *         "source": "app",
     *         "reconciliation_status": "matched",
     *         "linked_transaction_id": "GW-98765"
     *       }
     *     ]
     *   },
     *   "summary": {
     *     "total_matched": 145,
     *     "total_unmatched_app": 3,
     *     "total_unmatched_gateway": 2,
     *     "total_amount_mismatch": 15000.50,
     *     "total_app_amount": 725000.00,
     *     "total_gateway_amount": 740000.50,
     *     "total_discrepancy": 15000.50
     *   }
     * }
     * @response 403 {"message": "This action is unauthorized"}
     * @response 404 {"message": "Reconciliation run not found"}
     */
    public function show(ReconciliationRun $run)
    {
        $this->authorize('view', $run);

        return response()->json([
            'run' => $run->load('items'),
            'summary' => [
                'total_matched' => $run->total_matched,
                'total_unmatched_app' => $run->total_unmatched_app,
                'total_unmatched_gateway' => $run->total_unmatched_gateway,
                'total_amount_mismatch' => $run->total_amount_mismatch,
                'total_app_amount' => $run->total_app_amount,
                'total_gateway_amount' => $run->total_gateway_amount,
                'total_discrepancy' => $run->total_discrepancy,
            ],
        ]);
    }

    /**
     * Trigger a new reconciliation run.
     *
     * Initiates a new reconciliation process matching app transactions against gateway transactions.
     * Supports three modes: dry-run (rollback), sync (immediate), and queued (background).
     * Supports advanced tolerance configuration for fuzzy matching on amounts, dates, and references.
     * For dry-run: executes in database transaction then rolls back.
     * For sync: executes immediately and returns run results.
     * For queued (default): dispatches to queue and returns immediately with queued status.
     *
     * @authenticated
     * @bodyParam dry_run boolean Execute in transaction and rollback without saving. Example: false
     * @bodyParam sync boolean Execute immediately instead of queuing. Example: false
     * @bodyParam period_start date Filter transactions from this date (YYYY-MM-DD). Example: 2025-01-01
     * @bodyParam period_end date Filter transactions until this date (YYYY-MM-DD). Example: 2025-12-31
     * @bodyParam county string Filter transactions by county. Example: Nairobi
     * @bodyParam amount_percentage_tolerance number Percentage tolerance for amount matching (0-1). Example: 0.01
     * @bodyParam amount_absolute_tolerance number Absolute tolerance in currency units. Example: 100.00
     * @bodyParam date_tolerance integer Days tolerance for date matching. Example: 3
     * @bodyParam fuzzy_match_threshold integer Levenshtein similarity threshold 0-100. Example: 80
     * @response 200 {
     *   "message": "Dry run completed successfully (changes rolled back)",
     *   "run": {
     *     "id": 1,
     *     "run_id": "RUN-2025-001",
     *     "status": "success",
     *     "total_matched": 145,
     *     "total_unmatched_app": 3,
     *     "total_unmatched_gateway": 2
     *   },
     *   "dry_run": true
     * }
     * @response 201 {
     *   "message": "Reconciliation run completed successfully",
     *   "run": {
     *     "id": 1,
     *     "run_id": "RUN-2025-001",
     *     "status": "success",
     *     "total_matched": 145,
     *     "total_unmatched_app": 3,
     *     "total_unmatched_gateway": 2
     *   }
     * }
     * @response 202 {
     *   "message": "Reconciliation run queued for processing",
     *   "status": "queued"
     * }
     * @response 400 {"message": "Failed to trigger reconciliation: [error details]"}
     * @response 403 {"message": "This action is unauthorized"}
     * @response 422 {"message": "The given data was invalid", "errors": {"field": ["error message"]}}
     */
    public function trigger(TriggerReconciliationRequest $request)
    {
        $this->authorize('trigger', ReconciliationRun::class);

        $validated = $request->validated();

        // Prepare options
        $options = [
            'period_start' => $validated['period_start'] ?? null,
            'period_end' => $validated['period_end'] ?? null,
            'county' => $validated['county'] ?? null,
        ];

        if (isset($validated['amount_percentage_tolerance'])) {
            $options['amount_percentage_tolerance'] = $validated['amount_percentage_tolerance'];
        }
        if (isset($validated['amount_absolute_tolerance'])) {
            $options['amount_absolute_tolerance'] = $validated['amount_absolute_tolerance'];
        }
        if (isset($validated['date_tolerance'])) {
            $options['date_tolerance'] = $validated['date_tolerance'];
        }
        if (isset($validated['fuzzy_match_threshold'])) {
            $options['fuzzy_match_threshold'] = $validated['fuzzy_match_threshold'];
        }

        // For now, use empty transaction arrays (in production, fetch from actual sources)
        $appTransactions = [];
        $gatewayTransactions = [];

        if ($validated['dry_run'] ?? false) {
            // Dry run: execute in transaction and rollback
            try {
                \Illuminate\Support\Facades\DB::beginTransaction();
                $run = $this->service->runFromData($appTransactions, $gatewayTransactions, $options);
                $run->update(['created_by' => auth()->id()]);
                \Illuminate\Support\Facades\DB::rollBack();

                return response()->json([
                    'message' => 'Dry run completed successfully (changes rolled back)',
                    'run' => $run,
                    'dry_run' => true,
                ], Response::HTTP_OK);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                return response()->json([
                    'message' => 'Dry run failed: ' . $e->getMessage(),
                    'error' => $e->getMessage(),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Queue or sync execution
        try {
            if ($validated['sync'] ?? false) {
                $run = $this->service->runFromData($appTransactions, $gatewayTransactions, $options);
                $run->update(['created_by' => auth()->id()]);

                return response()->json([
                    'message' => 'Reconciliation run completed successfully',
                    'run' => $run,
                ], Response::HTTP_CREATED);
            } else {
                // Dispatch to queue
                RunReconciliationJob::dispatch($appTransactions, $gatewayTransactions, $options, auth()->id());

                return response()->json([
                    'message' => 'Reconciliation run queued for processing',
                    'status' => 'queued',
                ], Response::HTTP_ACCEPTED);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to trigger reconciliation: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Export reconciliation run items as CSV.
     *
     * Streams a CSV file containing reconciliation items (transactions) from a specific run.
     * Supports filtering by reconciliation status. CSV includes transaction details, amounts,
     * source (app/gateway), and linked transaction pairs for matched items.
     * Useful for reporting, auditing, and detailed reconciliation review.
     *
     * @authenticated
     * @queryParam run_id integer ID of the reconciliation run (required). Example: 1
     * @queryParam status string Filter by status: matched, unmatched_app, unmatched_gateway, amount_mismatch. Example: matched
     * @response 200 application/csv {
     *   Transaction ID,Reference,Amount,Source,Status,Date,Linked Transaction ID
     *   APP-12345,PESAPAL-REF-001,5000.00,app,matched,2025-01-15,GW-98765
     *   APP-12346,PESAPAL-REF-002,3500.50,app,matched,2025-01-15,GW-98766
     * }
     * @response 403 {"message": "This action is unauthorized"}
     * @response 404 {"message": "Reconciliation run not found"}
     * @response 422 {"message": "The given data was invalid", "errors": {"run_id": ["The run id field is required"]}}
     */
    public function export(ExportReconciliationRequest $request)
    {
        $validated = $request->validated();

        $run = ReconciliationRun::findOrFail($validated['run_id']);
        $this->authorize('export', $run);

        $query = $run->items();

        // Filter by status if provided
        if ($request->filled('status')) {
            $query->where('reconciliation_status', $request->input('status'));
        }

        $items = $query->get();

        // Stream CSV response
        $response = new StreamedResponse(function () use ($items, $run) {
            $handle = fopen('php://output', 'w');

            // CSV headers
            fputcsv($handle, [
                'Transaction ID',
                'Reference',
                'Amount',
                'Source',
                'Status',
                'Date',
                'Linked Transaction ID',
            ]);

            // Write items
            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->transaction_id,
                    $item->reference,
                    $item->amount,
                    $item->source,
                    $item->reconciliation_status,
                    $item->date ?? '',
                    $item->linked_transaction_id ?? '',
                ]);
            }

            fclose($handle);
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="reconciliation-run-' . $run->run_id . '.csv"',
        ]);

        return $response;
    }

    /**
     * Get reconciliation statistics and aggregate metrics.
     *
     * Returns high-level statistics across all reconciliation runs with optional date range filtering.
     * Useful for dashboards and reporting to show overall reconciliation health,
     * success rates, discrepancy totals, and transaction flow metrics.
     *
     * @authenticated
     * @queryParam period_start date Filter runs from this date (YYYY-MM-DD). Example: 2025-01-01
     * @queryParam period_end date Filter runs until this date (YYYY-MM-DD). Example: 2025-12-31
     * @response 200 {
     *   "total_runs": 45,
     *   "successful_runs": 42,
     *   "failed_runs": 3,
     *   "total_matched_across_runs": 6847,
     *   "total_unmatched_app": 125,
     *   "total_unmatched_gateway": 98,
     *   "total_app_amount": 28450000.00,
     *   "total_gateway_amount": 28465000.50,
     *   "total_discrepancy": 15000.50
     * }
     * @response 403 {"message": "This action is unauthorized"}
     */
    public function stats(Request $request)
    {
        $this->authorize('viewAny', ReconciliationRun::class);

        $query = ReconciliationRun::query();

        // Filter by date range
        if ($request->filled('period_start')) {
            $query->whereDate('started_at', '>=', $request->input('period_start'));
        }
        if ($request->filled('period_end')) {
            $query->whereDate('started_at', '<=', $request->input('period_end'));
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_runs,
            SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as successful_runs,
            SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_runs,
            SUM(total_matched) as total_matched_across_runs,
            SUM(total_unmatched_app) as total_unmatched_app,
            SUM(total_unmatched_gateway) as total_unmatched_gateway,
            SUM(total_app_amount) as total_app_amount,
            SUM(total_gateway_amount) as total_gateway_amount,
            SUM(total_discrepancy) as total_discrepancy
        ')->first();

        return response()->json($stats);
    }

    /**
     * Delete a reconciliation run and its items.
     *
     * Soft-deletes a reconciliation run and all associated items from the database.
     * Deleted records are retained for audit purposes but excluded from list/stats queries.
     * This is useful for removing erroneous runs or test data.
     *
     * @authenticated
     * @response 200 {"message": "Reconciliation run deleted successfully"}
     * @response 403 {"message": "This action is unauthorized"}
     * @response 404 {"message": "Reconciliation run not found"}
     */
    public function destroy(ReconciliationRun $run)
    {
        $this->authorize('delete', $run);

        $run->items()->delete();
        $run->delete();

        return response()->json([
            'message' => 'Reconciliation run deleted successfully',
        ]);
    }
}
