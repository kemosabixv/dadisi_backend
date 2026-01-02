<?php

namespace Tests\Feature\Services\Reconciliation;

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
 * Test suite for FinancialReconciliationService covering:
 * - Donation reconciliation
 * - Payment reconciliation
 * - Report generation
 * - Discrepancy flagging
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

    // ============ Donation Reconciliation Tests ============

    #[Test]
    public function it_can_reconcile_donations(): void
    {
        Donation::factory(5)->create([
            'amount' => 5000,
            'status' => 'verified',
            'created_at' => now()->subDay(),
        ]);

        Donation::factory(2)->create([
            'amount' => 3000,
            'status' => 'unverified',
            'created_at' => now()->subDay(),
        ]);

        $result = $this->service->reconcileDonations($this->admin, [
            'date_from' => now()->subDays(2),
            'date_to' => now(),
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('donations', $result['entity']);
        $this->assertEquals(7, $result['total_count']);
        $this->assertEquals(31000, $result['total_amount']); // 5*5000 + 2*3000
        $this->assertEquals(5, $result['verified_count']);
        $this->assertEquals(25000, $result['verified_amount']);
        $this->assertEquals(2, $result['unverified_count']);
        $this->assertEquals(6000, $result['unverified_amount']);
        $this->assertTrue($result['discrepancy']); // Has unverified donations
    }

    #[Test]
    public function it_can_reconcile_donations_without_discrepancies(): void
    {
        Donation::factory(3)->create([
            'amount' => 1000,
            'status' => 'verified',
            'created_at' => now()->subDay(),
        ]);

        $result = $this->service->reconcileDonations($this->admin, [
            'date_from' => now()->subDays(2),
            'date_to' => now(),
        ]);

        $this->assertFalse($result['discrepancy']);
    }

    #[Test]
    public function it_creates_audit_log_on_donation_reconciliation(): void
    {
        Donation::factory()->create();

        $this->service->reconcileDonations($this->admin, [
            'date_from' => now()->subDay(),
            'date_to' => now(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'reconciled_donations',
        ]);
    }

    // ============ Payment Reconciliation Tests ============

    #[Test]
    public function it_can_reconcile_payments(): void
    {
        Payment::factory(5)->create([
            'amount' => 10000,
            'status' => 'completed',
            'created_at' => now()->subDay(),
        ]);

        Payment::factory(2)->create([
            'amount' => 5000,
            'status' => 'pending',
            'created_at' => now()->subDay(),
        ]);

        Payment::factory(1)->create([
            'amount' => 2000,
            'status' => 'failed',
            'created_at' => now()->subDay(),
        ]);

        $result = $this->service->reconcilePayments($this->admin, [
            'date_from' => now()->subDays(2),
            'date_to' => now(),
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('payments', $result['entity']);
        $this->assertEquals(8, $result['total_count']);
        $this->assertEquals(62000, $result['total_amount']); // 5*10000 + 2*5000 + 1*2000
        $this->assertEquals(5, $result['completed_count']);
        $this->assertEquals(50000, $result['completed_amount']);
        $this->assertEquals(2, $result['pending_count']);
        $this->assertEquals(10000, $result['pending_amount']);
        $this->assertEquals(1, $result['failed_count']);
        $this->assertTrue($result['discrepancy']); // Has pending/failed payments
    }

    #[Test]
    public function it_can_reconcile_payments_without_discrepancies(): void
    {
        Payment::factory(3)->create([
            'amount' => 5000,
            'status' => 'completed',
            'created_at' => now()->subDay(),
        ]);

        $result = $this->service->reconcilePayments($this->admin, [
            'date_from' => now()->subDays(2),
            'date_to' => now(),
        ]);

        $this->assertFalse($result['discrepancy']);
    }

    #[Test]
    public function it_creates_audit_log_on_payment_reconciliation(): void
    {
        Payment::factory()->create();

        $this->service->reconcilePayments($this->admin, [
            'date_from' => now()->subDay(),
            'date_to' => now(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'reconciled_payments',
        ]);
    }

    // ============ Report Generation Tests ============

    #[Test]
    public function it_can_generate_reconciliation_report(): void
    {
        Payment::factory(3)->create(['amount' => 5000, 'status' => 'completed']);
        Donation::factory(2)->create(['amount' => 3000, 'status' => 'verified']);

        $report = $this->service->generateReconciliationReport($this->admin, [
            'date_from' => now()->subDays(2),
            'date_to' => now(),
        ]);

        $this->assertIsArray($report);
        $this->assertArrayHasKey('donations', $report);
        $this->assertArrayHasKey('payments', $report);
        $this->assertArrayHasKey('has_discrepancies', $report);
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertEquals($this->admin->id, $report['generated_by']);
    }

    #[Test]
    public function report_includes_correct_totals(): void
    {
        Payment::factory(2)->create(['amount' => 1000, 'status' => 'completed']);
        Donation::factory(3)->create(['amount' => 500, 'status' => 'verified']);

        $report = $this->service->generateReconciliationReport($this->admin, [
            'date_from' => now()->subDay(),
            'date_to' => now(),
        ]);

        $this->assertEquals(2000, $report['payments']['total_amount']);
        $this->assertEquals(1500, $report['donations']['total_amount']);
    }

    // ============ Discrepancy Flagging Tests ============

    #[Test]
    public function it_can_flag_discrepancy(): void
    {
        $result = $this->service->flagDiscrepancy(
            $this->admin,
            'Payment',
            123,
            'Amount mismatch detected'
        );

        $this->assertTrue($result);
    }

    #[Test]
    public function it_creates_audit_log_on_flag(): void
    {
        $this->service->flagDiscrepancy(
            $this->admin,
            'Donation',
            456,
            'Missing receipt'
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'flagged_discrepancy',
            'model_id' => 456,
        ]);
    }

    // ============ Status Tests ============

    #[Test]
    public function it_can_get_reconciliation_status_for_donations(): void
    {
        $status = $this->service->getReconciliationStatus('donations');

        $this->assertEquals('donations', $status['entity']);
        $this->assertArrayHasKey('status', $status);
    }

    #[Test]
    public function it_can_get_reconciliation_status_for_payments(): void
    {
        $status = $this->service->getReconciliationStatus('payments');

        $this->assertEquals('payments', $status['entity']);
        $this->assertArrayHasKey('status', $status);
    }

    #[Test]
    public function it_returns_error_for_unknown_entity(): void
    {
        $status = $this->service->getReconciliationStatus('unknown');

        $this->assertEquals('error', $status['status']);
    }

    // ============ Edge Cases ============

    #[Test]
    public function it_handles_empty_reconciliation_period(): void
    {
        $result = $this->service->reconcileDonations($this->admin, [
            'date_from' => now()->subDays(10),
            'date_to' => now()->subDays(9),
        ]);

        $this->assertEquals(0, $result['total_count']);
        $this->assertEquals(0, $result['total_amount']);
    }

    #[Test]
    public function it_filters_by_date_range(): void
    {
        // Donations outside range
        Donation::factory(2)->create([
            'amount' => 1000,
            'status' => 'verified',
            'created_at' => now()->subDays(10),
        ]);

        // Donations inside range
        Donation::factory(3)->create([
            'amount' => 2000,
            'status' => 'verified',
            'created_at' => now()->subDay(),
        ]);

        $result = $this->service->reconcileDonations($this->admin, [
            'date_from' => now()->subDays(2),
            'date_to' => now(),
        ]);

        $this->assertEquals(3, $result['total_count']);
        $this->assertEquals(6000, $result['total_amount']);
    }
}
