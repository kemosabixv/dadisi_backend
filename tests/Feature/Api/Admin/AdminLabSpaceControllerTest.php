<?php

namespace Tests\Feature\Api\Admin;

use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminLabSpaceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function authenticatedRequest(User $user, string $method, string $uri, array $data = []): TestResponse
    {
        return $this->actingAs($user, 'web')->json($method, $uri, $data);
    }

    private function createStaff(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    // ==================== Disable Bookings Tests ====================

    public function test_unauthorized_user_cannot_disable_bookings(): void
    {
        $space = LabSpace::factory()->create();
        $member = User::factory()->create();
        $member->assignRole('member');

        $response = $this->authenticatedRequest(
            $member,
            'POST',
            "/api/admin/spaces/{$space->id}/bookings/disable",
            ['reason' => 'Maintenance']
        );

        $response->assertStatus(403);
    }

    public function test_lab_manager_can_disable_bookings(): void
    {
        $space = LabSpace::factory()->create(['bookings_enabled' => true]);
        $manager = $this->createStaff('lab_manager');

        $response = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/spaces/{$space->id}/bookings/disable",
            ['reason' => 'Maintenance scheduled']
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.bookings_enabled', false);

        $this->assertFalse($space->fresh()->bookings_enabled);
    }

    public function test_admin_can_disable_bookings(): void
    {
        $space = LabSpace::factory()->create(['bookings_enabled' => true]);
        $admin = $this->createStaff('admin');

        $response = $this->authenticatedRequest(
            $admin,
            'POST',
            "/api/admin/spaces/{$space->id}/bookings/disable",
            ['reason' => 'Lab closure']
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertFalse($space->fresh()->bookings_enabled);
    }

    public function test_lab_supervisor_cannot_disable_bookings(): void
    {
        $space = LabSpace::factory()->create(['bookings_enabled' => true]);
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space->id);

        $response = $this->authenticatedRequest(
            $supervisor,
            'POST',
            "/api/admin/spaces/{$space->id}/bookings/disable"
        );

        $response->assertStatus(403);
    }

    public function test_disable_bookings_is_idempotent(): void
    {
        $space = LabSpace::factory()->create(['bookings_enabled' => false]);
        $manager = $this->createStaff('lab_manager');

        $response = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/spaces/{$space->id}/bookings/disable"
        );

        $response->assertStatus(200);
        $this->assertFalse($space->fresh()->bookings_enabled);
    }

    // ==================== Enable Bookings Tests ====================

    public function test_lab_manager_can_enable_bookings(): void
    {
        $space = LabSpace::factory()->create(['bookings_enabled' => false]);
        $manager = $this->createStaff('lab_manager');

        $response = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/spaces/{$space->id}/bookings/enable"
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.bookings_enabled', true);

        $this->assertTrue($space->fresh()->bookings_enabled);
    }

    public function test_admin_can_enable_bookings(): void
    {
        $space = LabSpace::factory()->create(['bookings_enabled' => false]);
        $admin = $this->createStaff('admin');

        $response = $this->authenticatedRequest(
            $admin,
            'POST',
            "/api/admin/spaces/{$space->id}/bookings/enable"
        );

        $response->assertStatus(200);
        $this->assertTrue($space->fresh()->bookings_enabled);
    }

    public function test_lab_supervisor_cannot_enable_bookings(): void
    {
        $space = LabSpace::factory()->create(['bookings_enabled' => false]);
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space->id);

        $response = $this->authenticatedRequest(
            $supervisor,
            'POST',
            "/api/admin/spaces/{$space->id}/bookings/enable"
        );

        $response->assertStatus(403);
    }

    public function test_enable_bookings_is_idempotent(): void
    {
        $space = LabSpace::factory()->create(['bookings_enabled' => true]);
        $manager = $this->createStaff('lab_manager');

        $response = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/spaces/{$space->id}/bookings/enable"
        );

        $response->assertStatus(200);
        $this->assertTrue($space->fresh()->bookings_enabled);
    }

    // ==================== List Lab Bookings Tests ====================

    public function test_unauthorized_user_cannot_list_lab_bookings(): void
    {
        $space = LabSpace::factory()->create();
        $member = User::factory()->create();
        $member->assignRole('member');

        $response = $this->authenticatedRequest($member, 'GET', "/api/admin/spaces/{$space->id}/bookings");

        $response->assertStatus(403);
    }

    public function test_lab_manager_can_list_all_lab_bookings(): void
    {
        $space = LabSpace::factory()->create();
        $manager = $this->createStaff('lab_manager');

        LabBooking::factory(3)->create(['lab_space_id' => $space->id]);

        $response = $this->authenticatedRequest($manager, 'GET', "/api/admin/spaces/{$space->id}/bookings");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_lab_supervisor_can_only_list_assigned_lab_bookings(): void
    {
        $space1 = LabSpace::factory()->create();
        $space2 = LabSpace::factory()->create();
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space1->id);

        LabBooking::factory(2)->create(['lab_space_id' => $space1->id]);
        LabBooking::factory(2)->create(['lab_space_id' => $space2->id]);

        $response = $this->authenticatedRequest($supervisor, 'GET', "/api/admin/spaces/{$space1->id}/bookings");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_lab_supervisor_cannot_list_unassigned_lab_bookings(): void
    {
        $space1 = LabSpace::factory()->create();
        $space2 = LabSpace::factory()->create();
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space1->id);

        LabBooking::factory(2)->create(['lab_space_id' => $space2->id]);

        $response = $this->authenticatedRequest($supervisor, 'GET', "/api/admin/spaces/{$space2->id}/bookings");

        $response->assertStatus(403);
    }

    public function test_list_lab_bookings_supports_status_filter(): void
    {
        $space = LabSpace::factory()->create();
        $manager = $this->createStaff('lab_manager');

        LabBooking::factory(2)->create(['lab_space_id' => $space->id, 'status' => 'CONFIRMED']);
        LabBooking::factory(1)->create(['lab_space_id' => $space->id, 'status' => 'CANCELLED']);

        $response = $this->authenticatedRequest(
            $manager,
            'GET',
            "/api/admin/spaces/{$space->id}/bookings?status=CONFIRMED"
        );

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_list_lab_bookings_supports_date_range_filter(): void
    {
        $space = LabSpace::factory()->create();
        $manager = $this->createStaff('lab_manager');

        $now = now();
        LabBooking::factory()->create([
            'lab_space_id' => $space->id,
            'starts_at' => $now->addDays(1),
        ]);
        LabBooking::factory()->create([
            'lab_space_id' => $space->id,
            'starts_at' => $now->subDays(5),
        ]);

        $dateFrom = $now->subDays(2)->format('Y-m-d');
        $dateTo = $now->addDays(5)->format('Y-m-d');

        $response = $this->authenticatedRequest(
            $manager,
            'GET',
            "/api/admin/spaces/{$space->id}/bookings?date_from={$dateFrom}&date_to={$dateTo}"
        );

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_list_lab_bookings_supports_pagination(): void
    {
        $space = LabSpace::factory()->create();
        $manager = $this->createStaff('lab_manager');

        LabBooking::factory(25)->create(['lab_space_id' => $space->id]);

        $response = $this->authenticatedRequest(
            $manager,
            'GET',
            "/api/admin/spaces/{$space->id}/bookings?per_page=10"
        );

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.per_page', 10);
        $response->assertJsonPath('meta.total', 25);
    }
}
