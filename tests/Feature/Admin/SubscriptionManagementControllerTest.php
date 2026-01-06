<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Plan;
use App\Models\PlanSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

use PHPUnit\Framework\Attributes\Test;

class SubscriptionManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $superAdmin;
    protected $nonAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions for both guards
        $viewPermission = Permission::create(['name' => 'view_subscriptions', 'guard_name' => 'api']);
        $managePermission = Permission::create(['name' => 'manage_subscriptions', 'guard_name' => 'api']);
        
        Permission::create(['name' => 'view_subscriptions', 'guard_name' => 'web']);
        Permission::create(['name' => 'manage_subscriptions', 'guard_name' => 'web']);

        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'api']);
        $adminRole->givePermissionTo([$viewPermission, $managePermission]);
        
        $superAdminRole = Role::create(['name' => 'super_admin', 'guard_name' => 'api']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole($adminRole);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole($superAdminRole);

        $this->nonAdmin = User::factory()->create();
    }

    #[Test]
    public function super_admin_can_list_subscriptions()
    {
        PlanSubscription::factory()->count(3)->create();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/admin/subscriptions');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function admin_can_list_subscriptions()
    {
        PlanSubscription::factory()->count(2)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/subscriptions');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function unauthorized_user_cannot_list_subscriptions()
    {
        $response = $this->actingAs($this->nonAdmin, 'sanctum')
            ->getJson('/api/admin/subscriptions');

        $response->assertStatus(403);
    }

    #[Test]
    public function can_filter_subscriptions_by_status()
    {
        PlanSubscription::factory()->create(['status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth()]);
        PlanSubscription::factory()->create(['status' => 'expired', 'starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/subscriptions?status=active');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'active');
    }

    #[Test]
    public function can_search_subscriptions_by_user_name()
    {
        $user = User::factory()->create();
        \App\Models\MemberProfile::factory()->create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        
        PlanSubscription::factory()->create([
            'subscriber_id' => $user->id,
            'subscriber_type' => User::class
        ]);
        
        PlanSubscription::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/subscriptions?search=John');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_name', 'John Doe');
    }

    #[Test]
    public function can_view_single_subscription_details()
    {
        $subscription = PlanSubscription::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/subscriptions/{$subscription->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $subscription->id);
    }
}
