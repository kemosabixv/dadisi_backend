<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Refund;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        \Spatie\Permission\Models\Role::create(['name' => 'admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_unauthenticated_user_cannot_access_refunds(): void
    {
        $response = $this->getJson('/api/admin/refunds');

        $response->assertStatus(401);
    }

    public function test_non_admin_user_cannot_access_refunds(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/admin/refunds');

        $response->assertStatus(403);
    }

    public function test_admin_can_list_refunds(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/refunds');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    public function test_admin_can_get_refund_stats(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/refunds/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'pending',
                    'approved',
                    'completed',
                    'total_refunded',
                ],
            ]);
    }

    public function test_admin_can_view_refund_details(): void
    {
        $payment = Payment::factory()->create();
        $refund = Refund::factory()->create([
            'payment_id' => $payment->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/refunds/{$refund->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'amount',
                    'status',
                    'reason',
                ],
            ]);
    }

    public function test_admin_can_approve_pending_refund(): void
    {
        $payment = Payment::factory()->create();
        $refund = Refund::factory()->create([
            'payment_id' => $payment->id,
            'status' => Refund::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/refunds/{$refund->id}/approve");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals(Refund::STATUS_APPROVED, $refund->fresh()->status);
    }

    public function test_admin_cannot_approve_non_pending_refund(): void
    {
        $payment = Payment::factory()->create();
        $refund = Refund::factory()->create([
            'payment_id' => $payment->id,
            'status' => Refund::STATUS_COMPLETED,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/refunds/{$refund->id}/approve");

        $response->assertStatus(400);
    }

    public function test_admin_can_reject_refund_with_reason(): void
    {
        $payment = Payment::factory()->create();
        $refund = Refund::factory()->create([
            'payment_id' => $payment->id,
            'status' => Refund::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/refunds/{$refund->id}/reject", [
                'admin_notes' => 'Refund period has expired',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals(Refund::STATUS_REJECTED, $refund->fresh()->status);
    }

    public function test_admin_must_provide_reason_for_rejection(): void
    {
        $payment = Payment::factory()->create();
        $refund = Refund::factory()->create([
            'payment_id' => $payment->id,
            'status' => Refund::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/refunds/{$refund->id}/reject");

        // API returns 400 for missing required fields
        $response->assertStatus(400);
    }

    public function test_admin_can_filter_refunds_by_status(): void
    {
        $payment = Payment::factory()->create();
        
        Refund::factory()->create([
            'payment_id' => $payment->id,
            'status' => Refund::STATUS_PENDING,
        ]);
        
        Refund::factory()->create([
            'payment_id' => $payment->id,
            'status' => Refund::STATUS_COMPLETED,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/refunds?status=pending');

        $response->assertStatus(200);
        
        // Check success and data exists
        $response->assertJson(['success' => true]);
    }
}
