<?php

namespace Tests\Feature\Admin;

use App\Models\PlanSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected $superAdmin;

    protected $nonAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure web guard is used for permissions checks in tests
        config(['auth.defaults.guard' => 'web']);
        \Illuminate\Support\Facades\Auth::shouldUse('web');

        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

        // Create custom permissions for subscription management testing
        $viewPermission = Permission::firstOrCreate(['name' => 'view_subscriptions', 'guard_name' => 'web']);
        $managePermission = Permission::firstOrCreate(['name' => 'manage_subscriptions', 'guard_name' => 'web']);

        // Get seeded roles and add permissions (using web guard)
        $adminRole = Role::findByName('admin', 'web');
        $adminRole->givePermissionTo([$viewPermission, $managePermission]);

        $superAdminRole = Role::findByName('super_admin', 'web');

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

        $this->actingAs($this->superAdmin);
        $response = $this->getJson('/api/admin/subscriptions');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function admin_can_list_subscriptions()
    {
        PlanSubscription::factory()->count(2)->create();

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/subscriptions');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function unauthorized_user_cannot_list_subscriptions()
    {
        $this->actingAs($this->nonAdmin);
        $response = $this->getJson('/api/admin/subscriptions');

        $response->assertStatus(403);
    }

    #[Test]
    public function can_filter_subscriptions_by_status()
    {
        PlanSubscription::factory()->create(['status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth()]);
        PlanSubscription::factory()->create(['status' => 'expired', 'starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/subscriptions?status=active');

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
            'last_name' => 'Doe',
        ]);

        PlanSubscription::factory()->create([
            'subscriber_id' => $user->id,
            'subscriber_type' => 'user',
        ]);

        PlanSubscription::factory()->create();

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/subscriptions?search=John');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_name', 'John Doe');
    }

    #[Test]
    public function can_view_single_subscription_details()
    {
        $subscription = PlanSubscription::factory()->create();

        $this->actingAs($this->admin);
        $response = $this->getJson("/api/admin/subscriptions/{$subscription->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.current.id', $subscription->id);
    }
}
