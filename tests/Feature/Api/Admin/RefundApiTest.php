<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RefundApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    private User $finance;

    private User $labManager;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Super Admin (has all permissions)
        $this->superAdmin = User::factory()->create(['username' => 'superadmin']);
        $this->superAdmin->assignRole('super_admin');

        // Admin (has manage_refunds)
        $this->admin = User::factory()->create(['username' => 'admin_user']);
        $this->admin->assignRole('admin');

        // Finance (has manage_refunds)
        $this->finance = User::factory()->create(['username' => 'finance_user']);
        $this->finance->assignRole('finance');

        // Lab Manager (has admin access role but NOT manage_refunds permission)
        $this->labManager = User::factory()->create(['username' => 'lab_manager_user']);
        $this->labManager->assignRole('lab_manager');

        // Regular Member (no admin access)
        $this->member = User::factory()->create(['username' => 'member_user']);
        $this->member->assignRole('member');
    }

    /**
     * Helper to perform an authenticated request using a manual token.
     * This bypasses actingAs issues in some test environments.
     */
    private function authenticatedRequest(User $user, string $method, string $uri, array $data = []): TestResponse
    {
        return $this->actingAs($user, 'web')->json($method, $uri, $data);
    }

    public function test_unauthenticated_user_cannot_access_refunds(): void
    {
        $response = $this->getJson('/api/admin/refunds');
        $response->assertStatus(401);
    }

    public function test_member_cannot_access_admin_refunds(): void
    {
        $response = $this->authenticatedRequest($this->member, 'GET', '/api/admin/refunds');
        $response->assertStatus(403);
    }

    public function test_lab_manager_can_access_admin_but_not_manage_refunds(): void
    {
        // Lab manager passes AdminMiddleware but should fail permission check in controller
        $response = $this->authenticatedRequest($this->labManager, 'GET', '/api/admin/refunds');
        $response->assertStatus(403);
    }

    public function test_admin_can_list_refunds(): void
    {
        $response = $this->authenticatedRequest($this->admin, 'GET', '/api/admin/refunds');
        $response->assertStatus(200);
    }

    public function test_finance_can_list_refunds(): void
    {
        $response = $this->authenticatedRequest($this->finance, 'GET', '/api/admin/refunds');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_list_refunds(): void
    {
        $response = $this->authenticatedRequest($this->superAdmin, 'GET', '/api/admin/refunds');
        $response->assertStatus(200);
    }

    public function test_staff_can_view_refund_stats(): void
    {
        $response = $this->authenticatedRequest($this->admin, 'GET', '/api/admin/refunds/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['pending', 'approved', 'completed', 'total_refunded'],
            ]);
    }

    public function test_staff_can_approve_pending_refund(): void
    {
        $payment = Payment::factory()->create(['status' => 'paid']);
        $refund = Refund::factory()->create([
            'payment_id' => $payment->id,
            'status' => Refund::STATUS_PENDING,
            'refundable_type' => 'event_order',
            'refundable_id' => 1,
        ]);

        $response = $this->authenticatedRequest($this->finance, 'POST', "/api/admin/refunds/{$refund->id}/approve");

        $response->assertStatus(200);
        $this->assertEquals(Refund::STATUS_APPROVED, $refund->fresh()->status);
    }

    public function test_staff_can_reject_refund_without_reason(): void
    {
        $this->withoutExceptionHandling();
        $payment = Payment::factory()->create(['status' => 'paid']);
        $refund = Refund::factory()->create([
            'payment_id' => $payment->id,
            'status' => Refund::STATUS_PENDING,
            'refundable_type' => 'event_order',
            'refundable_id' => 1,
        ]);

        $response = $this->authenticatedRequest($this->admin, 'POST', "/api/admin/refunds/{$refund->id}/reject", []);

        $response->assertStatus(200);
        $this->assertEquals(Refund::STATUS_REJECTED, $refund->fresh()->status);
    }
}
