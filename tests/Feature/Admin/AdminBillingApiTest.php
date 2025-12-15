<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Donation;
use App\Models\EventOrder;
use App\Models\Event;
use Spatie\Permission\Models\Role;

class AdminBillingApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles/permissions and create an admin user with finances role
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

        $this->adminUser = User::factory()->create(['email' => 'admin-billing@example.com']);

        $adminRole = Role::findByName('admin');
        $financeRole = Role::findByName('finance');

        // Give the user the finance role if available, otherwise admin
        if ($financeRole) {
            $this->adminUser->assignRole($financeRole);
        } else {
            $this->adminUser->assignRole($adminRole);
        }
    }

    public function test_billing_dashboard_returns_expected_structure()
    {
        // Create sample donations and event orders
        // Insert sample donations without factories
        Donation::create(['donor_name' => 'Alice', 'donor_email' => 'alice@example.com', 'amount' => 100, 'status' => 'paid', 'currency' => 'KES']);
        Donation::create(['donor_name' => 'Bob', 'donor_email' => 'bob@example.com', 'amount' => 50, 'status' => 'pending', 'currency' => 'KES']);
        Donation::create(['donor_name' => 'Charlie', 'donor_email' => 'charlie@example.com', 'amount' => 20, 'status' => 'failed', 'currency' => 'KES']);

        // Create a minimal event for event orders
        $event = Event::create([
            'title' => 'Test Event',
            'slug' => 'test-event',
            'description' => 'A test event',
            'venue' => 'Test Venue',
            'starts_at' => now(),
            'ends_at' => now()->addHours(2),
            'status' => 'published',
            'price' => 200,
            'currency' => 'KES',
        ]);

        // Insert sample event orders without factories
        EventOrder::create(['event_id' => $event->id, 'total_amount' => 200, 'status' => 'paid', 'currency' => 'KES', 'quantity' => 1, 'unit_price' => 200]);
        EventOrder::create(['event_id' => $event->id, 'total_amount' => 75, 'status' => 'pending', 'currency' => 'KES', 'quantity' => 1, 'unit_price' => 75]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/billing/dashboard');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'success',
            'data' => [
                'donations' => ['total', 'paid', 'pending', 'failed', 'total_amount', 'pending_amount'],
                'event_orders' => ['total', 'paid', 'pending', 'failed', 'total_revenue', 'pending_revenue'],
                'combined_total',
                'combined_pending',
                'last_30_days_total',
            ],
        ]);

        $json = $response->json('data');

        $this->assertEquals(3, $json['donations']['total']);
        $this->assertEquals(1, $json['donations']['paid']);
        $this->assertEquals(1, $json['donations']['pending']);

        $this->assertEquals(2, $json['event_orders']['total']);
        $this->assertEquals(1, $json['event_orders']['paid']);
    }
}
