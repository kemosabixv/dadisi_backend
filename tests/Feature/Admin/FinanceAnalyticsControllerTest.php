<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Payment;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
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

        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->financeStaff = User::factory()->create();
        $this->financeStaff->assignRole('finance');

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    #[Test]
    public function finance_staff_can_view_analytics()
    {
        // Create some sample data
        Payment::factory()->create(['status' => 'paid', 'amount' => 1000]);
        Payment::factory()->create(['status' => 'refunded', 'amount' => 200, 'refunded_at' => now()]);

        $this->actingAs($this->financeStaff);
        $response = $this->getJson('/api/admin/finance/analytics');

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
        $this->actingAs($this->superAdmin);
        $response = $this->getJson('/api/admin/finance/analytics');

        $response->assertStatus(200);
    }

    #[Test]
    public function admin_can_view_analytics()
    {
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/finance/analytics');

        $response->assertStatus(200);
    }

    #[Test]
    public function unauthorized_user_cannot_view_analytics()
    {
        $regularUser = User::factory()->create();

        $this->actingAs($regularUser);
        $response = $this->getJson('/api/admin/finance/analytics');

        $response->assertStatus(403);
    }

    #[Test]
    public function it_can_filter_analytics_by_period()
    {
        $this->actingAs($this->financeStaff);
        $response = $this->getJson('/api/admin/finance/analytics?period=year');

        $response->assertStatus(200)
            ->assertJsonPath('data.period', 'year');
    }
}
