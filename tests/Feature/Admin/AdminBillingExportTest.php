<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use App\Services\BillingExportService;

class AdminBillingExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

        $this->adminUser = User::factory()->create(['email' => 'admin-export@example.com']);

        $financeRole = Role::findByName('finance');
        $adminRole = Role::findByName('admin');

        if ($financeRole) {
            $this->adminUser->assignRole($financeRole);
        } else {
            $this->adminUser->assignRole($adminRole);
        }

        // Disable middleware for controller permission checks during tests
        $this->withoutMiddleware();
    }

    public function test_export_donations_returns_csv_stream()
    {
        $csv = "donor,amount\nAlice,100\n";

        $this->mock(BillingExportService::class, function ($mock) use ($csv) {
            $mock->shouldReceive('exportDonations')->once()->andReturn($csv);
            $mock->shouldReceive('generateFilename')->once()->andReturn('donations.csv');
        });

        $date = now()->format('Y-m-d');
        $response = $this->actingAs($this->adminUser)
            ->get('/api/admin/billing/export/donations?start_date=' . $date . '&end_date=' . $date);

        $response->assertStatus(200);

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('donations.csv', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Alice,100', $response->streamedContent());
    }

    public function test_export_event_orders_returns_csv_stream()
    {
        $csv = "order_id,total\n1,200\n";

        $this->mock(BillingExportService::class, function ($mock) use ($csv) {
            $mock->shouldReceive('exportEventOrders')->once()->andReturn($csv);
            $mock->shouldReceive('generateFilename')->once()->andReturn('event_orders.csv');
        });

        $date = now()->format('Y-m-d');
        $response = $this->actingAs($this->adminUser)
            ->get('/api/admin/billing/export/event-orders?start_date=' . $date . '&end_date=' . $date);

        $response->assertStatus(200);

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('event_orders.csv', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('1,200', $response->streamedContent());
    }
}
