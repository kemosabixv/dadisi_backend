<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * FinanceAnalyticsController
 *
 * Provides analytical data for finance dashboards.
 */
class FinanceAnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified']);
    }

    /**
     * Get revenue and refund stats over time
     *
     * @authenticated
     * @queryParam period string Selection: day, week, month, year. Default: month.
     */
    public function stats(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('view_finance_analytics'), 403, 'Unauthorized');

        $period = $request->get('period', 'month');

        $startDate = $this->getStartDate($period);

        try {
            // Get the database driver to use appropriate date functions
            $driver = DB::connection()->getDriverName();
            $dateFormat = $this->getDateExpression($period, 'created_at', $driver);
            $refundDateFormat = $this->getDateExpression($period, 'refunded_at', $driver);
            
            $revenueData = Payment::where('status', 'paid')
                ->where('created_at', '>=', $startDate)
                ->select([
                    DB::raw("{$dateFormat} as date"),
                    DB::raw('SUM(amount) as total_revenue'),
                    DB::raw('COUNT(*) as transaction_count')
                ])
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            $refundData = Payment::where('status', 'refunded')
                ->where('refunded_at', '>=', $startDate)
                ->select([
                    DB::raw("{$refundDateFormat} as date"),
                    DB::raw('SUM(amount) as total_refunded'),
                    DB::raw('COUNT(*) as refund_count')
                ])
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            // Category breakdown
            $categoryData = Payment::where('status', 'paid')
                ->where('created_at', '>=', $startDate)
                ->select([
                    'payable_type',
                    DB::raw('SUM(amount) as total'),
                    DB::raw('COUNT(*) as count')
                ])
                ->groupBy('payable_type')
                ->get()
                ->map(function($item) {
                    $type = $item->payable_type;
                    if (str_contains($type, 'EventOrder')) $label = 'Events';
                    elseif (str_contains($type, 'Donation')) $label = 'Donations';
                    elseif (str_contains($type, 'Subscription')) $label = 'Subscriptions';
                    else $label = 'Other';
                    
                    return [
                        'label' => $label,
                        'total' => (float) $item->total,
                        'count' => $item->count
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'revenue' => $revenueData,
                    'refunds' => $refundData,
                    'categories' => $categoryData,
                    'period' => $period,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Finance Analytics Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get database-agnostic date expression
     */
    protected function getDateExpression(string $period, string $column, string $driver): string
    {
        if ($driver === 'sqlite') {
            return match($period) {
                'day' => "strftime('%Y-%m-%d %H:00', {$column})",
                'week' => "strftime('%Y-%m-%d', {$column})",
                'month' => "strftime('%Y-%m-%d', {$column})",
                'year' => "strftime('%Y-%m', {$column})",
                default => "strftime('%Y-%m-%d', {$column})",
            };
        }
        
        // MySQL/MariaDB
        $format = $this->getGroupByFormat($period);
        return "DATE_FORMAT({$column}, '{$format}')";
    }


    /**
     * Helper to get start date based on period
     */
    protected function getStartDate(string $period): Carbon
    {
        return match($period) {
            'day' => now()->startOfDay(),
            'week' => now()->subWeeks(1),
            'month' => now()->subMonths(1),
            'year' => now()->subYears(1),
            default => now()->subMonths(1),
        };
    }

    /**
     * Helper to get MySQL DATE_FORMAT based on period
     */
    protected function getGroupByFormat(string $period): string
    {
        return match($period) {
            'day' => '%Y-%m-%d %H:00', // Hourly for day
            'week' => '%Y-%m-%d',     // Daily for week
            'month' => '%Y-%m-%d',    // Daily for month
            'year' => '%Y-%m',       // Monthly for year
            default => '%Y-%m-%d',
        };
    }
}
