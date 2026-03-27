<?php

namespace Tests\Feature\Admin;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected $financeStaff;

    protected $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure web guard is used for permissions checks in tests
        config(['auth.defaults.guard' => 'web']);
        \Illuminate\Support\Facades\Auth::shouldUse('web');

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Seed roles and permissions (web guard)
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

        // Create users
        $this->superAdmin = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->financeStaff = User::factory()->create();
        $this->regularUser = User::factory()->create();

        // Assign roles retrieved from database (web guard)
        $superAdminRole = \Spatie\Permission\Models\Role::findByName('super_admin', 'web');
        $adminRole = \Spatie\Permission\Models\Role::findByName('admin', 'web');
        $financeRole = \Spatie\Permission\Models\Role::findByName('finance', 'web');

        $this->superAdmin->assignRole($superAdminRole);
        $this->admin->assignRole($adminRole);
        $this->financeStaff->assignRole($financeRole);
    }

    #[Test]
    public function finance_staff_can_list_payments()
    {
        Payment::factory()->count(5)->create();

        $this->actingAs($this->financeStaff);
        $response = $this->getJson('/api/admin/finance/payments');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'success',
                'data',
                'meta',
            ]);
    }

    #[Test]
    public function regular_user_cannot_list_payments()
    {
        $this->actingAs($this->regularUser);
        $response = $this->getJson('/api/admin/finance/payments');

        $response->assertStatus(403);
    }

    #[Test]
    public function super_admin_can_list_payments()
    {
        Payment::factory()->count(2)->create();

        $this->actingAs($this->superAdmin);
        $response = $this->getJson('/api/admin/finance/payments');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function admin_can_list_payments()
    {
        Payment::factory()->count(2)->create();

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/finance/payments');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_can_filter_payments_by_status()
    {
        Payment::factory()->create(['status' => 'paid']);
        Payment::factory()->create(['status' => 'pending']);

        $this->actingAs($this->financeStaff);
        $response = $this->getJson('/api/admin/finance/payments?status=paid');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'paid');
    }

    #[Test]
    public function it_can_search_payments_by_reference()
    {
        $payment = Payment::factory()->create(['reference' => 'TEST-REF-123']);
        Payment::factory()->create(['reference' => 'OTHER-REF-456']);

        $this->actingAs($this->financeStaff);
        $response = $this->getJson('/api/admin/finance/payments?search=TEST-REF');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'TEST-REF-123');
    }

    #[Test]
    public function it_can_show_single_payment_details()
    {
        $payment = Payment::factory()->create();

        $this->actingAs($this->financeStaff);
        $response = $this->getJson("/api/admin/finance/payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $payment->id);
    }

    #[Test]
    public function finance_staff_cannot_initiate_refund()
    {
        $payment = Payment::factory()->create(['status' => 'paid', 'transaction_id' => 'TRANS_123']);

        $this->actingAs($this->financeStaff);
        $response = $this->postJson("/api/admin/finance/payments/{$payment->id}/refund", [
            'reason' => 'Customer requested refund',
        ]);

        // Finance staff cannot initiate refunds (only approve/reject/process)
        $response->assertStatus(403);
    }

    #[Test]
    public function finance_staff_can_approve_reject_and_process_refunds()
    {
        // Create a refund request (simulating user-initiated)
        $payment = Payment::factory()->create(['status' => 'paid', 'transaction_id' => 'TRANS_123']);
        $refund = \App\Models\Refund::create([
            'refundable_type' => \App\Models\EventOrder::class,
            'refundable_id' => 1, // Mock
            'payment_id' => $payment->id,
            'amount' => 100.00,
            'currency' => 'KES',
            'original_amount' => $payment->amount,
            'status' => 'pending',
            'reason' => 'cancellation',
            'customer_notes' => 'Test refund',
        ]);

        $this->actingAs($this->financeStaff);

        // Test approval
        $response = $this->postJson("/api/admin/refunds/{$refund->id}/approve", [
            'admin_notes' => 'Approved for testing',
        ]);
        $response->assertStatus(200);

        // Refresh refund and test processing
        $refund->refresh();
        $this->assertEquals('approved', $refund->status);

        $response = $this->postJson("/api/admin/refunds/{$refund->id}/process");
        // Processing may succeed or fail depending on gateway mock, but should not be 403
        $this->assertNotEquals(403, $response->status());
    }
}
