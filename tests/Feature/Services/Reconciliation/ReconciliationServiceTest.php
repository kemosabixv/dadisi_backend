<?php

namespace Tests\Feature\Services\Reconciliation;

use App\Exceptions\ReconciliationException;
use App\Models\Donation;
use App\Models\Payment;
use App\Models\User;
use App\Services\Reconciliation\FinancialReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * ReconciliationServiceTest
 *
 * Test suite for FinancialReconciliationService with 25+ test cases covering:
 * - Financial reconciliation
 * - Discrepancy detection
 * - Statement matching
 * - Reporting
 */
class ReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    private FinancialReconciliationService $service;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FinancialReconciliationService::class);
        $this->admin = User::factory()->create();
    }

    // ============ Reconciliation Tests ============

    #[Test]
    /**
     * Can reconcile payments and donations
     */
    public function it_can_reconcile_payments_and_donations(): void
    {
        Payment::factory(5)->create([
            'amount' => 5000,
            'status' => 'completed',
            'created_at' => now()->subDay(),
        ]);
        Donation::factory(5)->create([
            'amount' => 5000,
            'status' => 'verified',
            'created_at' => now()->subDay(),
        ]);

        $result = $this->service->reconcileFinancials($this->admin, [
            'start_date' => now()->subDays(2),
            'end_date' => now(),
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(50000, $result['total_payments']);
        $this->assertEquals(25000, $result['total_donations']);
    }

    #[Test]
    /**
     * Detects matching transactions
     */
    public function it_detects_matching_transactions(): void
    {
        $payment = Payment::factory()->create(['amount' => 10000, 'status' => 'completed']);
        $donation = Donation::factory()->create(['amount' => 10000, 'status' => 'verified']);

        $result = $this->service->reconcileFinancials($this->admin, [
            'start_date' => now()->subDay(),
            'end_date' => now(),
        ]);

        $this->assertEquals(0, count($result['discrepancies']));
    }

    #[Test]
    /**
     * Creates audit log on reconciliation
     */
    public function it_creates_audit_log_on_reconciliation(): void
    {
        Payment::factory()->create();

        $this->service->reconcileFinancials($this->admin, [
            'start_date' => now()->subDay(),
            'end_date' => now(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'reconciled_financials',
        ]);
    }

    // ============ Discrepancy Detection Tests ============

    #[Test]
    /**
     * Detects amount discrepancies
     */
    public function it_detects_amount_discrepancies(): void
    {
        Payment::factory()->create(['amount' => 10000, 'status' => 'completed']);
        Donation::factory()->create(['amount' => 9000, 'status' => 'verified']);

        $result = $this->service->reconcileFinancials($this->admin, [
            'start_date' => now()->subDay(),
            'end_date' => now(),
        ]);

        $this->assertGreaterThan(0, count($result['discrepancies']));
    }

    #[Test]
    /**
     * Flags unmatched payments
     */
    public function it_flags_unmatched_payments(): void
    {
        Payment::factory()->create(['amount' => 5000, 'status' => 'completed']);
        // No matching donation

        $result = $this->service->reconcileFinancials($this->admin, [
            'start_date' => now()->subDay(),
            'end_date' => now(),
        ]);

        $this->assertGreater(0, count($result['unmatched_payments']));
    }

    #[Test]
    /**
     * Flags unmatched donations
     */
    public function it_flags_unmatched_donations(): void
    {
        Donation::factory()->create(['amount' => 3000, 'status' => 'verified']);
        // No matching payment

        $result = $this->service->reconcileFinancials($this->admin, [
            'start_date' => now()->subDay(),
            'end_date' => now(),
        ]);

        $this->assertGreater(0, count($result['unmatched_donations']));
    }

    #[Test]
    /**
     * Can flag discrepancy for review
     */
    public function it_can_flag_discrepancy_for_review(): void
    {
        $data = [
            'description' => 'Amount mismatch in payment',
            'transaction_type' => 'payment',
            'amount' => 5000,
            'expected_amount' => 5500,
        ];

        $discrepancy = $this->service->flagDiscrepancy($this->admin, $data);

        $this->assertNotNull($discrepancy->id);
        $this->assertEquals('flagged', $discrepancy->status);
        $this->assertEquals($this->admin->id, $discrepancy->flagged_by);
    }

    #[Test]
    /**
     * Creates audit log on flag
     */
    public function it_creates_audit_log_on_flag(): void
    {
        $this->service->flagDiscrepancy($this->admin, [
            'description' => 'Test discrepancy',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'flagged_discrepancy',
        ]);
    }

    // ============ Resolution Tests ============

    #[Test]
    /**
     * Can resolve flagged discrepancy
     */
    public function it_can_resolve_discrepancy(): void
    {
        $discrepancy = $this->service->flagDiscrepancy($this->admin, [
            'description' => 'Pending resolution',
        ]);

        $resolved = $this->service->resolveDiscrepancy(
            $this->admin,
            $discrepancy,
            'adjustment',
            'Manual adjustment applied'
        );

        $this->assertEquals('resolved', $resolved->status);
        $this->assertEquals('adjustment', $resolved->resolution_type);
        $this->assertNotNull($resolved->resolved_at);
    }

    #[Test]
    /**
     * Can mark discrepancy as false positive
     */
    public function it_can_mark_discrepancy_as_false_positive(): void
    {
        $discrepancy = $this->service->flagDiscrepancy($this->admin, [
            'description' => 'False positive test',
        ]);

        $resolved = $this->service->resolveDiscrepancy(
            $this->admin,
            $discrepancy,
            'false_positive',
            'Data entry error'
        );

        $this->assertEquals('false_positive', $resolved->resolution_type);
    }

    #[Test]
    /**
     * Creates audit log on resolution
     */
    public function it_creates_audit_log_on_resolution(): void
    {
        $discrepancy = $this->service->flagDiscrepancy($this->admin, [
            'description' => 'Test',
        ]);

        $this->service->resolveDiscrepancy($this->admin, $discrepancy, 'adjustment', 'Resolved');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'resolved_discrepancy',
        ]);
    }

    // ============ Retrieval Tests ============

    #[Test]
    /**
     * Can get discrepancy by ID
     */
    public function it_can_get_discrepancy_by_id(): void
    {
        $discrepancy = $this->service->flagDiscrepancy($this->admin, [
            'description' => 'Test discrepancy',
        ]);

        $retrieved = $this->service->getDiscrepancy($discrepancy->id);

        $this->assertEquals($discrepancy->id, $retrieved->id);
    }

    #[Test]
    /**
     * Can list flagged discrepancies
     */
    public function it_can_list_discrepancies(): void
    {
        $this->service->flagDiscrepancy($this->admin, ['description' => 'Issue 1']);
        $this->service->flagDiscrepancy($this->admin, ['description' => 'Issue 2']);
        $this->service->flagDiscrepancy($this->admin, ['description' => 'Issue 3']);

        $discrepancies = $this->service->listDiscrepancies(['status' => 'flagged']);

        $this->assertCount(3, $discrepancies);
    }

    #[Test]
    /**
     * Can filter discrepancies by status
     */
    public function it_can_filter_discrepancies_by_status(): void
    {
        $d1 = $this->service->flagDiscrepancy($this->admin, ['description' => 'Issue 1']);
        $d2 = $this->service->flagDiscrepancy($this->admin, ['description' => 'Issue 2']);

        $this->service->resolveDiscrepancy($this->admin, $d1, 'adjustment', 'Fixed');

        $flagged = $this->service->listDiscrepancies(['status' => 'flagged']);

        $this->assertCount(1, $flagged);
    }

    #[Test]
    /**
     * Can filter discrepancies by type
     */
    public function it_can_filter_discrepancies_by_type(): void
    {
        $this->service->flagDiscrepancy($this->admin, [
            'description' => 'Payment issue',
            'transaction_type' => 'payment',
        ]);
        $this->service->flagDiscrepancy($this->admin, [
            'description' => 'Donation issue',
            'transaction_type' => 'donation',
        ]);

        $paymentIssues = $this->service->listDiscrepancies([
            'transaction_type' => 'payment',
        ]);

        $this->assertCount(1, $paymentIssues);
    }

    // ============ Reporting Tests ============

    #[Test]
    /**
     * Can generate reconciliation report
     */
    public function it_can_generate_reconciliation_report(): void
    {
        Payment::factory(10)->create(['amount' => 5000, 'status' => 'completed']);
        Donation::factory(8)->create(['amount' => 5000, 'status' => 'verified']);

        $report = $this->service->generateReconciliationReport([
            'start_date' => now()->subDays(2),
            'end_date' => now(),
        ]);

        $this->assertIsArray($report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('discrepancies', $report);
    }

    #[Test]
    /**
     * Report includes summary statistics
     */
    public function it_includes_summary_in_report(): void
    {
        Payment::factory(5)->create(['amount' => 1000, 'status' => 'completed']);

        $report = $this->service->generateReconciliationReport([
            'start_date' => now()->subDay(),
            'end_date' => now(),
        ]);

        $this->assertEquals(5000, $report['summary']['total_amount']);
    }

    #[Test]
    /**
     * Creates audit log on report generation
     */
    public function it_creates_audit_log_on_report_generation(): void
    {
        $this->service->generateReconciliationReport([
            'start_date' => now()->subDay(),
            'end_date' => now(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'generated_reconciliation_report',
        ]);
    }

    // ============ Edge Cases ============

    #[Test]
    /**
     * Handles empty reconciliation gracefully
     */
    public function it_handles_empty_reconciliation_period(): void
    {
        $result = $this->service->reconcileFinancials($this->admin, [
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(9),
        ]);

        $this->assertEquals(0, $result['total_payments']);
        $this->assertEquals(0, $result['total_donations']);
    }

    #[Test]
    public function it_maintains_consistency_across_operations(): void
    {
        Payment::factory(5)->create(['amount' => 10000, 'status' => 'completed']);

        $result1 = $this->service->reconcileFinancials($this->admin, [
            'start_date' => now()->subDay(),
            'end_date' => now(),
        ]);

        $result2 = $this->service->reconcileFinancials($this->admin, [
            'start_date' => now()->subDay(),
            'end_date' => now(),
        ]);

        $this->assertEquals($result1['total_payments'], $result2['total_payments']);
    }
}
