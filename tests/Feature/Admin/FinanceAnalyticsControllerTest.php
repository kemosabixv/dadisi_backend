<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use PHPUnit\Framework\Attributes\Test;

class FinanceAnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $financeStaff;
    protected $superAdmin;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles and permissions for BOTH guards (web and api)
        $financeRoleWeb = Role::findOrCreate('finance', 'web');
        $financeRoleApi = Role::findOrCreate('finance', 'api');
        $superAdminRoleWeb = Role::findOrCreate('super_admin', 'web');
        $superAdminRoleApi = Role::findOrCreate('super_admin', 'api');
        $adminRoleWeb = Role::findOrCreate('admin', 'web');
        $adminRoleApi = Role::findOrCreate('admin', 'api');
        
        $viewAnalyticsWeb = Permission::findOrCreate('view_finance_analytics', 'web');
        $viewAnalyticsApi = Permission::findOrCreate('view_finance_analytics', 'api');
        
        $financeRoleWeb->givePermissionTo($viewAnalyticsWeb);
        $financeRoleApi->givePermissionTo($viewAnalyticsApi);
        $superAdminRoleWeb->givePermissionTo($viewAnalyticsWeb);
        $superAdminRoleApi->givePermissionTo($viewAnalyticsApi);
        $adminRoleWeb->givePermissionTo($viewAnalyticsWeb);
        $adminRoleApi->givePermissionTo($viewAnalyticsApi);

        $this->financeStaff = User::factory()->create();
        $this->financeStaff->assignRole([$financeRoleWeb, $financeRoleApi]);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole([$superAdminRoleWeb, $superAdminRoleApi]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole([$adminRoleWeb, $adminRoleApi]);
    }

    #[Test]
    public function finance_staff_can_view_analytics()
    {
        // Create some sample data
        Payment::factory()->create(['status' => 'paid', 'amount' => 1000]);
        Payment::factory()->create(['status' => 'refunded', 'amount' => 200, 'refunded_at' => now()]);

        $response = $this->actingAs($this->financeStaff)
            ->getJson('/api/admin/finance/analytics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'revenue',
                    'refunds',
                    'categories',
                    'period'
                ]
            ]);
    }

    #[Test]
    public function super_admin_can_view_analytics()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/admin/finance/analytics');

        $response->assertStatus(200);
    }

    #[Test]
    public function admin_can_view_analytics()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/finance/analytics');

        $response->assertStatus(200);
    }

    #[Test]
    public function unauthorized_user_cannot_view_analytics()
    {
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser)
            ->getJson('/api/admin/finance/analytics');

        $response->assertStatus(403);
    }

    #[Test]
    public function it_can_filter_analytics_by_period()
    {
        $response = $this->actingAs($this->financeStaff)
            ->getJson('/api/admin/finance/analytics?period=year');

        $response->assertStatus(200)
            ->assertJsonPath('data.period', 'year');
    }
}
