<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\ReconciliationRun;
use App\Models\ReconciliationItem;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class ReconciliationAdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $financeUser;
    protected User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions and roles
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

        // Create users with different roles
        $this->adminUser = User::factory()->create(['email' => 'admin@example.com']);
        $this->financeUser = User::factory()->create(['email' => 'finance@example.com']);
        $this->unauthorizedUser = User::factory()->create(['email' => 'user@example.com']);

        // Assign roles
        $adminRole = Role::findByName('admin');
        $financeRole = Role::findByName('finance');
        $memberRole = Role::findByName('member');

        $this->adminUser->assignRole($adminRole);
        $this->financeUser->assignRole($financeRole);
        $this->unauthorizedUser->assignRole($memberRole);
    }

    /**
     * Test: List reconciliation runs (view permission required)
     */
    public function test_list_reconciliation_runs_with_view_permission()
    {
        // Create sample runs
        ReconciliationRun::factory()->count(3)->create();

        $response = $this->actingAs($this->adminUser)
            ->get('/api/admin/reconciliation');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'status', 'total_matched', 'total_unmatched_app', 'created_at'],
            ],
            'pagination',
        ]);
    }

    /**
     * Test: List reconciliation runs - unauthorized user denied
     */
    public function test_list_reconciliation_runs_unauthorized()
    {
        ReconciliationRun::factory()->count(3)->create();

        $response = $this->actingAs($this->unauthorizedUser)
            ->get('/api/admin/reconciliation');

        $response->assertStatus(403);
    }

    /**
     * Test: Show specific reconciliation run with details
     */
    public function test_show_reconciliation_run_details()
    {
        $run = ReconciliationRun::factory()->create([
            'status' => 'success',
            'total_matched' => 10,
            'total_unmatched_app' => 1,
            'total_unmatched_gateway' => 2,
        ]);

        // Create items
        ReconciliationItem::factory()->count(5)->create([
            'reconciliation_run_id' => $run->id,
            'reconciliation_status' => 'matched',
        ]);

        ReconciliationItem::factory()->count(3)->create([
            'reconciliation_run_id' => $run->id,
            'reconciliation_status' => 'unmatched_app',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get("/api/admin/reconciliation/{$run->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'run',
            'summary' => [
                'total_matched',
                'total_unmatched_app',
                'total_unmatched_gateway',
            ],
        ]);
    }

    /**
     * Test: Get reconciliation statistics
     */
    public function test_get_reconciliation_stats()
    {
        // Create multiple runs with different statuses
        ReconciliationRun::factory()->create(['status' => 'success', 'total_matched' => 100]);
        ReconciliationRun::factory()->create(['status' => 'success', 'total_matched' => 95]);
        ReconciliationRun::factory()->create(['status' => 'running']);

        $response = $this->actingAs($this->financeUser)
            ->get('/api/admin/reconciliation/stats');

        $response->assertStatus(200);
        // Stats returns aggregate data
        $this->assertIsNumeric($response->json('total_runs'));
        $this->assertIsNumeric($response->json('total_matched_across_runs'));
    }

    /**
     * Test: Trigger reconciliation with dry-run
     */
    public function test_trigger_reconciliation_dry_run()
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/api/admin/reconciliation/trigger', [
                'dry_run' => true,
                'sync' => false,
                'amount_percentage_tolerance' => 1,
                'date_tolerance' => 3,
                'fuzzy_match_threshold' => 80,
            ]);

        $response->assertStatus(200); // Dry run returns 200
        $response->assertJsonStructure([
            'message',
            'run',
        ]);
    }

    /**
     * Test: Trigger reconciliation with sync mode
     */
    public function test_trigger_reconciliation_sync()
    {
        $response = $this->actingAs($this->financeUser)
            ->post('/api/admin/reconciliation/trigger', [
                'dry_run' => false,
                'sync' => true,
                'amount_percentage_tolerance' => 1,
                'date_tolerance' => 3,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'run' => [
                'id',
                'status',
                'total_matched',
                'total_unmatched_app',
                'total_unmatched_gateway',
            ],
        ]);
    }

    /**
     * Test: Trigger reconciliation - requires manage permission
     */
    public function test_trigger_reconciliation_unauthorized()
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->post('/api/admin/reconciliation/trigger', [
                'dry_run' => true,
                'sync' => false,
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test: Export reconciliation run as CSV
     */
    public function test_export_reconciliation_run_csv()
    {
        $run = ReconciliationRun::factory()->create(['status' => 'success']);

        ReconciliationItem::factory()->count(10)->create([
            'reconciliation_run_id' => $run->id,
            'reconciliation_status' => 'matched',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get("/api/admin/reconciliation/export?run_id={$run->id}");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('reconciliation-run-' . $run->run_id, $response->headers->get('Content-Disposition'));
    }

    /**
     * Test: Export reconciliation run with status filter
     */
    public function test_export_reconciliation_run_with_filter()
    {
        $run = ReconciliationRun::factory()->create(['status' => 'success']);

        ReconciliationItem::factory()->count(5)->create([
            'reconciliation_run_id' => $run->id,
            'reconciliation_status' => 'matched',
        ]);

        ReconciliationItem::factory()->count(3)->create([
            'reconciliation_run_id' => $run->id,
            'reconciliation_status' => 'unmatched_app',
        ]);

        $response = $this->actingAs($this->financeUser)
            ->get("/api/admin/reconciliation/export?run_id={$run->id}&status=matched");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    /**
     * Test: Delete reconciliation run - requires manage permission
     */
    public function test_delete_reconciliation_run()
    {
        $run = ReconciliationRun::factory()->create();

        ReconciliationItem::factory()->count(5)->create([
            'reconciliation_run_id' => $run->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->delete("/api/admin/reconciliation/{$run->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['message']);

        $this->assertSoftDeleted('reconciliation_runs', ['id' => $run->id]);
        $this->assertSoftDeleted('reconciliation_items', ['reconciliation_run_id' => $run->id]);
    }

    /**
     * Test: Delete reconciliation run - unauthorized
     */
    public function test_delete_reconciliation_run_unauthorized()
    {
        $run = ReconciliationRun::factory()->create();

        $response = $this->actingAs($this->unauthorizedUser)
            ->delete("/api/admin/reconciliation/{$run->id}");

        $response->assertStatus(403);
    }

    /**
     * Test: Finance user can view and manage reconciliation
     */
    public function test_finance_user_permissions()
    {
        $run = ReconciliationRun::factory()->create();

        // View: should succeed
        $response = $this->actingAs($this->financeUser)
            ->get('/api/admin/reconciliation');
        $response->assertStatus(200);

        // Manage: should succeed
        $response = $this->actingAs($this->financeUser)
            ->post('/api/admin/reconciliation/trigger', [
                'dry_run' => true,
                'sync' => false,
            ]);
        $response->assertStatus(200);
    }

    /**
     * Test: Trigger reconciliation with invalid tolerances
     */
    public function test_trigger_reconciliation_invalid_tolerances()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/reconciliation/trigger', [
                'dry_run' => true,
                'sync' => false,
                'amount_percentage_tolerance' => 150, // Invalid: > 100
                'date_tolerance' => 3,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount_percentage_tolerance']);
    }

    /**
     * Test: Export without required run_id parameter
     */
    public function test_export_missing_run_id()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/reconciliation/export');

        $response->assertStatus(422);
    }

    /**
     * Test: Show non-existent run
     */
    public function test_show_nonexistent_run()
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/api/admin/reconciliation/9999');

        $response->assertStatus(404);
    }
}
