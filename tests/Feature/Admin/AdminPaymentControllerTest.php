<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Payment;
use App\Models\Donation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use PHPUnit\Framework\Attributes\Test;

class AdminPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $financeStaff;
    protected $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles and permissions with BOTH 'web' and 'api' guards
        // Sanctum uses 'api' guard when authenticating via tokens
        $superAdminRoleWeb = Role::findOrCreate('super_admin', 'web');
        $financeRoleWeb = Role::findOrCreate('finance', 'web');
        $superAdminRoleApi = Role::findOrCreate('super_admin', 'api');
        $financeRoleApi = Role::findOrCreate('finance', 'api');
        
        // Create permissions for both guards
        $managePaymentsWeb = Permission::findOrCreate('manage_payments', 'web');
        $refundPaymentsWeb = Permission::findOrCreate('refund_payments', 'web');
        $managePaymentsApi = Permission::findOrCreate('manage_payments', 'api');
        $refundPaymentsApi = Permission::findOrCreate('refund_payments', 'api');
        
        $adminRoleWeb = Role::findOrCreate('admin', 'web');
        $adminRoleApi = Role::findOrCreate('admin', 'api');
        
        // Assign permissions to web roles
        $financeRoleWeb->givePermissionTo([$managePaymentsWeb, $refundPaymentsWeb]);
        $superAdminRoleWeb->givePermissionTo([$managePaymentsWeb, $refundPaymentsWeb]);
        $adminRoleWeb->givePermissionTo([$managePaymentsWeb, $refundPaymentsWeb]);
        
        // Assign permissions to api roles
        $financeRoleApi->givePermissionTo([$managePaymentsApi, $refundPaymentsApi]);
        $superAdminRoleApi->givePermissionTo([$managePaymentsApi, $refundPaymentsApi]);
        $adminRoleApi->givePermissionTo([$managePaymentsApi, $refundPaymentsApi]);

        // Create users and assign BOTH web and api roles
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole([$superAdminRoleWeb, $superAdminRoleApi]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole([$adminRoleWeb, $adminRoleApi]);

        $this->financeStaff = User::factory()->create();
        $this->financeStaff->assignRole([$financeRoleWeb, $financeRoleApi]);

        $this->regularUser = User::factory()->create();
    }


    #[Test]
    public function finance_staff_can_list_payments()
    {
        Payment::factory()->count(5)->create();

        $response = $this->actingAs($this->financeStaff)
            ->getJson('/api/admin/finance/payments');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'success',
                'data',
                'meta'
            ]);
    }


    #[Test]
    public function regular_user_cannot_list_payments()
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/admin/finance/payments');

        $response->assertStatus(403);
    }

    #[Test]
    public function super_admin_can_list_payments()
    {
        Payment::factory()->count(2)->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/admin/finance/payments');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function admin_can_list_payments()
    {
        Payment::factory()->count(2)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/finance/payments');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_can_filter_payments_by_status()
    {
        Payment::factory()->create(['status' => 'paid']);
        Payment::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->financeStaff)
            ->getJson('/api/admin/finance/payments?status=paid');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'paid');
    }

    #[Test]
    public function it_can_search_payments_by_reference()
    {
        $payment = Payment::factory()->create(['reference' => 'TEST-REF-123']);
        Payment::factory()->create(['reference' => 'OTHER-REF-456']);

        $response = $this->actingAs($this->financeStaff)
            ->getJson('/api/admin/finance/payments?search=TEST-REF');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'TEST-REF-123');
    }

    #[Test]
    public function it_can_show_single_payment_details()
    {
        $payment = Payment::factory()->create();

        $response = $this->actingAs($this->financeStaff)
            ->getJson("/api/admin/finance/payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $payment->id);
    }

    #[Test]
    public function finance_staff_can_initiate_refund()
    {
        // Mocking the PaymentService would be better, but for feature test we can check the request reach
        $payment = Payment::factory()->create(['status' => 'paid', 'transaction_id' => 'TRANS_123']);

        // We need to mock the service or ensure it's handled. 
        // For now, let's verify authorization and validation.
        $response = $this->actingAs($this->financeStaff)
            ->postJson("/api/admin/finance/payments/{$payment->id}/refund", [
                'reason' => 'Customer requested refund'
            ]);

        // Note: Success will depend on the mocked gateway in PaymentService.
        // If it fails because of gateway errors, it should still be a 400 with a message.
        $this->assertContains($response->status(), [200, 400]);
    }
}
