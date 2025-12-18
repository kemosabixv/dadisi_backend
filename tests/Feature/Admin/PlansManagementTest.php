<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlansManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $ts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ts = time();
        $this->admin = User::create([
            'username' => 'planadmin_' . $this->ts,
            'email' => 'planadmin+' . $this->ts . '@example.com',
            'password' => 'password123',
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_can_list_plans()
    {
        Plan::create([
            'name' => json_encode(['en' => 'Basic Plan']),
            'slug' => 'basic-plan-' . $this->ts,
            'description' => json_encode(['en' => 'Basic Plan']),
            'price' => 1000,
            'base_monthly_price' => 1000,
            'currency' => 'KES',
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->admin)->getJson('/api/plans');

        $resp->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_can_create_plan_via_api()
    {
        $payload = [
            'name' => 'Test Plan ' . $this->ts,
            'slug' => 'test-plan-' . $this->ts,
            'monthly_price_kes' => 1500,
            'currency' => 'KES',
        ];

        $resp = $this->actingAs($this->admin)->postJson('/api/plans', $payload);

        $resp->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('plans', ['currency' => 'KES']);
    }

    public function test_can_update_plan()
    {
        $plan = Plan::create([
            'name' => json_encode(['en' => 'Old Plan']),
            'slug' => 'old-plan-' . $this->ts,
            'description' => json_encode(['en' => 'Old Plan']),
            'price' => 2000,
            'base_monthly_price' => 2000,
            'currency' => 'KES',
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->admin)->putJson('/api/plans/' . $plan->id, [
            'name' => 'Updated Plan ' . $this->ts,
            'monthly_price_kes' => 2200,
        ]);

        $resp->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_can_delete_plan()
    {
        $plan = Plan::create([
            'name' => json_encode(['en' => 'ToDelete']),
            'slug' => 'to-delete-' . $this->ts,
            'description' => json_encode(['en' => 'ToDelete']),
            'price' => 1200,
            'base_monthly_price' => 1200,
            'currency' => 'KES',
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->admin)->deleteJson('/api/plans/' . $plan->id);
        $resp->assertStatus(200);
    }
}
