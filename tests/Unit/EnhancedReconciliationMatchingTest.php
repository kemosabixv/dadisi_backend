<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Reconciliation\ReconciliationService;
use App\Models\ReconciliationRun;
use App\Models\ReconciliationItem;

class EnhancedReconciliationMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected ReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReconciliationService();
    }

    public function test_exact_transaction_id_match()
    {
        $app = [
            ['transaction_id' => 'TXN001', 'reference' => 'REF001', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];
        $gateway = [
            ['transaction_id' => 'TXN001', 'reference' => 'REF001', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];

        $run = $this->service->runFromData($app, $gateway);

        $this->assertSame(1, $run->total_matched);
        $this->assertSame(0, $run->total_unmatched_app);
        $this->assertSame(0, $run->total_unmatched_gateway);
    }

    public function test_exact_reference_match_with_amount_tolerance()
    {
        $app = [
            ['transaction_id' => 'A1', 'reference' => 'REF001', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'reference' => 'REF001', 'amount' => 100.50, 'date' => '2025-12-11'],
        ];

        // With 1% tolerance, 100 vs 100.50 should match (0.5% difference)
        $run = $this->service
            ->setAmountPercentageTolerance(0.01)
            ->runFromData($app, $gateway);

        $this->assertSame(1, $run->total_matched);
        $this->assertSame(0, $run->total_unmatched_app);
        $this->assertSame(0, $run->total_unmatched_gateway);
    }

    public function test_reference_match_fails_without_sufficient_amount_tolerance()
    {
        $app = [
            ['transaction_id' => 'A1', 'reference' => 'REF001', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'reference' => 'REF001', 'amount' => 105.00, 'date' => '2025-12-11'],
        ];

        // With 1% tolerance, 100 vs 105 should NOT match (5% difference)
        $run = $this->service
            ->setAmountPercentageTolerance(0.01)
            ->runFromData($app, $gateway);

        $this->assertSame(0, $run->total_matched);
        $this->assertSame(1, $run->total_unmatched_app);
        $this->assertSame(1, $run->total_unmatched_gateway);
    }

    public function test_fuzzy_reference_match_with_tolerances()
    {
        $app = [
            ['transaction_id' => 'A1', 'reference' => 'REF-001-ABC', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'reference' => 'REF-001-AC', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];

        // Fuzzy match with 80% threshold should match (similar references)
        $run = $this->service
            ->setFuzzyMatchThreshold(80)
            ->runFromData($app, $gateway);

        $this->assertSame(1, $run->total_matched);
        $this->assertSame(0, $run->total_unmatched_app);
        $this->assertSame(0, $run->total_unmatched_gateway);
    }

    public function test_fuzzy_match_respects_threshold()
    {
        $app = [
            ['transaction_id' => 'A1', 'reference' => 'ABCDEF', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'reference' => 'ZYXWVU', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];

        // Very different strings should not match even with fuzzy matching
        $run = $this->service
            ->setFuzzyMatchThreshold(90)
            ->runFromData($app, $gateway);

        $this->assertSame(0, $run->total_matched);
        $this->assertSame(1, $run->total_unmatched_app);
        $this->assertSame(1, $run->total_unmatched_gateway);
    }

    public function test_date_tolerance_allows_nearby_dates()
    {
        $app = [
            ['transaction_id' => 'A1', 'reference' => 'REF001', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'reference' => 'REF001', 'amount' => 100.00, 'date' => '2025-12-13'],
        ];

        // With 3-day tolerance, 2-day difference should match
        $run = $this->service
            ->setDateTolerance(3)
            ->runFromData($app, $gateway);

        $this->assertSame(1, $run->total_matched);
    }

    public function test_date_tolerance_rejects_far_dates()
    {
        $app = [
            ['transaction_id' => 'A1', 'reference' => 'FUZZY_REF', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'reference' => 'DIFFERENT_REF', 'amount' => 100.00, 'date' => '2025-12-20'],
        ];

        // With 3-day tolerance and exact reference mismatch, 9-day difference should NOT match
        // (dates are only checked during fuzzy matching or amount-only matching)
        $run = $this->service
            ->setDateTolerance(3)
            ->setFuzzyMatchThreshold(70)
            ->runFromData($app, $gateway);

        // Should not match: reference differs slightly and date tolerance doesn't apply to exact reference match
        $this->assertSame(0, $run->total_matched);
        $this->assertSame(1, $run->total_unmatched_app);
        $this->assertSame(1, $run->total_unmatched_gateway);
    }

    public function test_absolute_amount_tolerance()
    {
        $app = [
            ['transaction_id' => 'A1', 'reference' => 'REF001', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'reference' => 'REF001', 'amount' => 100.05, 'date' => '2025-12-11'],
        ];

        // With 0.10 absolute tolerance, 0.05 difference should match
        $run = $this->service
            ->setAmountAbsoluteTolerance(0.10)
            ->runFromData($app, $gateway);

        $this->assertSame(1, $run->total_matched);
    }

    public function test_amount_and_date_match_without_reference()
    {
        $app = [
            ['transaction_id' => 'A1', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];

        // Should match on transaction_id first
        $run = $this->service->runFromData($app, $gateway);

        $this->assertSame(1, $run->total_matched);
    }

    public function test_amount_and_date_match_without_any_identifiers()
    {
        $app = [
            ['amount' => 100.00, 'date' => '2025-12-11'],
        ];
        $gateway = [
            ['amount' => 100.00, 'date' => '2025-12-11'],
        ];

        // Should match on amount and date alone when no identifiers exist
        $run = $this->service->runFromData($app, $gateway);

        $this->assertSame(1, $run->total_matched);
    }

    public function test_multiple_transactions_with_mixed_matching()
    {
        $app = [
            ['transaction_id' => 'A1', 'reference' => 'REF001', 'amount' => 100.00, 'date' => '2025-12-11'],
            ['transaction_id' => 'A2', 'reference' => 'REF002', 'amount' => 50.00, 'date' => '2025-12-11'],
            ['transaction_id' => 'A3', 'amount' => 75.00, 'date' => '2025-12-11'], // No reference
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'reference' => 'REF001', 'amount' => 100.00, 'date' => '2025-12-11'],
            ['transaction_id' => 'G2', 'reference' => 'REF-002', 'amount' => 50.00, 'date' => '2025-12-11'], // Fuzzy match on reference
            ['transaction_id' => 'G3', 'amount' => 75.00, 'date' => '2025-12-11'], // Match on txn_id
        ];

        $run = $this->service
            ->setFuzzyMatchThreshold(70)
            ->runFromData($app, $gateway);

        $this->assertSame(3, $run->total_matched);
        $this->assertSame(0, $run->total_unmatched_app);
        $this->assertSame(0, $run->total_unmatched_gateway);
    }

    public function test_unmatched_gateway_transactions_recorded()
    {
        $app = [
            ['transaction_id' => 'A1', 'reference' => 'REF001', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'reference' => 'REF001', 'amount' => 100.00, 'date' => '2025-12-11'],
            ['transaction_id' => 'G2', 'reference' => 'NOMATCH', 'amount' => 50.00, 'date' => '2025-12-11'],
        ];

        $run = $this->service->runFromData($app, $gateway);

        $this->assertSame(1, $run->total_matched);
        $this->assertSame(0, $run->total_unmatched_app);
        $this->assertSame(1, $run->total_unmatched_gateway);

        // Verify the unmatched gateway item is recorded
        $unmatched = ReconciliationItem::where('reconciliation_run_id', $run->id)
            ->where('reconciliation_status', 'unmatched_gateway')
            ->first();
        $this->assertNotNull($unmatched);
        $this->assertSame('G2', $unmatched->transaction_id);
    }

    public function test_matching_priority_transaction_id_over_reference()
    {
        $app = [
            ['transaction_id' => 'A1', 'reference' => 'REF001', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];
        $gateway = [
            ['transaction_id' => 'A1', 'reference' => 'DIFFERENT', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];

        $run = $this->service->runFromData($app, $gateway);

        // Should match on transaction_id despite different reference
        $this->assertSame(1, $run->total_matched);
    }

    public function test_zero_amounts_always_match()
    {
        $app = [
            ['transaction_id' => 'A1', 'reference' => 'REF001', 'amount' => 0.00, 'date' => '2025-12-11'],
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'reference' => 'REF001', 'amount' => 0.00, 'date' => '2025-12-11'],
        ];

        $run = $this->service->runFromData($app, $gateway);

        // Zero amounts should match
        $this->assertSame(1, $run->total_matched);
    }

    public function test_missing_dates_dont_block_match()
    {
        $app = [
            ['transaction_id' => 'A1', 'reference' => 'REF001', 'amount' => 100.00],
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'reference' => 'REF001', 'amount' => 100.00, 'date' => '2025-12-11'],
        ];

        $run = $this->service->runFromData($app, $gateway);

        // Missing date should not prevent match
        $this->assertSame(1, $run->total_matched);
    }
}
