<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use App\Services\Reconciliation\DonationReconciliationService;
use App\Services\Reconciliation\EventOrderReconciliationService;

class AdminBillingReconcileTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

        $this->adminUser = User::factory()->create(['email' => 'admin-reconcile@example.com']);

        $financeRole = Role::findByName('finance');
        $adminRole = Role::findByName('admin');

        if ($financeRole) {
            $this->adminUser->assignRole($financeRole);
        } else {
            $this->adminUser->assignRole($adminRole);
        }

        // Disable middleware for these controller-level permission checks in unit tests
        $this->withoutMiddleware();
    }

    public function test_reconcile_donations_endpoint_returns_expected_structure()
    {
        $results = [
            'total_checked' => 3,
            'reconciled' => 2,
            'discrepancies' => 1,
            'errors' => [],
        ];

        $this->mock(DonationReconciliationService::class, function ($mock) use ($results) {
            $mock->shouldReceive('reconcileAll')->once()->andReturn($results);
        });

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/billing/reconcile/donations');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['total_checked', 'reconciled', 'discrepancies', 'errors']])
            ->assertJson(['success' => true, 'data' => $results]);
    }

    public function test_reconcile_orders_endpoint_returns_expected_structure()
    {
        $results = [
            'total_checked' => 2,
            'reconciled' => 2,
            'discrepancies' => 0,
            'errors' => [],
        ];

        $this->mock(EventOrderReconciliationService::class, function ($mock) use ($results) {
            $mock->shouldReceive('reconcileAll')->once()->andReturn($results);
        });

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/billing/reconcile/orders');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['total_checked', 'reconciled', 'discrepancies', 'errors']])
            ->assertJson(['success' => true, 'data' => $results]);
    }

    public function test_get_reconciliation_status_returns_discrepancy_structures()
    {
        $donationDiscrepancies = [
            'missing_payments' => [],
            'amount_mismatches' => [],
            'status_mismatches' => [],
        ];

        $orderDiscrepancies = [
            'missing_payments' => [],
            'amount_mismatches' => [],
            'quantity_issues' => [],
        ];

        $this->mock(DonationReconciliationService::class, function ($mock) use ($donationDiscrepancies) {
            $mock->shouldReceive('detectDiscrepancies')->once()->andReturn($donationDiscrepancies);
        });

        $this->mock(EventOrderReconciliationService::class, function ($mock) use ($orderDiscrepancies) {
            $mock->shouldReceive('detectDiscrepancies')->once()->andReturn($orderDiscrepancies);
        });

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/billing/reconcile/status');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['donation_discrepancies', 'order_discrepancies']])
            ->assertJson(['success' => true, 'data' => ['donation_discrepancies' => $donationDiscrepancies, 'order_discrepancies' => $orderDiscrepancies]]);
    }
}
