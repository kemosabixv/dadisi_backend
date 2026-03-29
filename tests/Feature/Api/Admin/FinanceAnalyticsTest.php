<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $financeManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        
        $this->financeManager = User::factory()->create();
        $this->financeManager->assignRole('finance_manager');
    }

    /**
     * Test that analytics exclude TestPayment model records
     */
    public function test_analytics_excludes_test_payment_model(): void
    {
        // Create a real paid payment
        Payment::factory()->create([
            'status' => 'paid',
            'amount' => 1000,
            'payable_type' => 'App\\Models\\EventOrder',
            'created_at' => now(),
        ]);

        // Create a TestPayment paid payment (should be excluded)
        Payment::factory()->create([
            'status' => 'paid',
            'amount' => 5000,
            'payable_type' => 'App\\Models\\TestPayment',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->financeManager, 'web')
            ->getJson('/api/admin/finance/analytics?period=month');

        $response->assertStatus(200);
        
        // Sum should be 1000, not 6000
        $this->assertEquals(1000, $response->json('data.summary.gross_revenue'));
    }

    /**
     * Test that analytics exclude payments with TEST- prefix
     */
    public function test_analytics_excludes_test_prefix_payments(): void
    {
        // Real payment
        Payment::factory()->create([
            'status' => 'paid',
            'amount' => 2000,
            'reference' => 'REAL-REF-123',
            'created_at' => now(),
        ]);

        // Prefixed test payments (should be excluded)
        Payment::factory()->create([
            'status' => 'paid',
            'amount' => 9999,
            'reference' => 'TEST-SANDBOX-001',
            'created_at' => now(),
        ]);

        Payment::factory()->create([
            'status' => 'paid',
            'amount' => 8888,
            'transaction_id' => 'TEST-TXN-002',
            'created_at' => now(),
        ]);

        Payment::factory()->create([
            'status' => 'paid',
            'amount' => 7777,
            'confirmation_code' => 'TEST-CONF-003',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->financeManager, 'web')
            ->getJson('/api/admin/finance/analytics?period=month');

        $response->assertStatus(200);
        
        // Sum should only include the real payment (2000)
        $this->assertEquals(2000, $response->json('data.summary.gross_revenue'));
    }

    /**
     * Test that analytics exclude refunds for test payments
     */
    public function test_analytics_excludes_test_prefix_refunds(): void
    {
        // Real refund
        Payment::factory()->create([
            'status' => 'refunded',
            'amount' => 300,
            'reference' => 'REAL-REF-REFUND',
            'refunded_at' => now(),
        ]);

        // Prefixed test refund (should be excluded)
        Payment::factory()->create([
            'status' => 'refunded',
            'amount' => 500,
            'reference' => 'TEST-REFUND-001',
            'refunded_at' => now(),
        ]);

        $response = $this->actingAs($this->financeManager, 'web')
            ->getJson('/api/admin/finance/analytics?period=month');

        $response->assertStatus(200);
        
        // Summary total_refunded should be 300, not 800
        $this->assertEquals(300, $response->json('data.summary.total_refunded'));
    }

    /**
     * Test that MOCK- prefixed records ARE included (since they were removed from exclusion list)
     */
    public function test_analytics_includes_mock_prefix_records(): void
    {
        // Payment with MOCK- prefix
        Payment::factory()->create([
            'status' => 'paid',
            'amount' => 450,
            'reference' => 'MOCK-PAYMENT-REF',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->financeManager, 'web')
            ->getJson('/api/admin/finance/analytics?period=month');

        $response->assertStatus(200);
        
        // Should include the MOCK- payment
        $this->assertGreaterThanOrEqual(450, $response->json('data.summary.gross_revenue'));
    }
}
