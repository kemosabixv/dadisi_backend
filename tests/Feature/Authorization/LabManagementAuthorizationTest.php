<?php

namespace Tests\Feature\Authorization;

use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabManagementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function createStaff(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    // ==================== Lab Space Policy Tests ====================

    public function test_lab_manager_can_disable_own_lab(): void
    {
        $space = LabSpace::factory()->create();
        $manager = $this->createStaff('lab_manager');

        $this->assertTrue($manager->can('disableBookings', $space));
    }

    public function test_lab_manager_can_enable_own_lab(): void
    {
        $space = LabSpace::factory()->create();
        $manager = $this->createStaff('lab_manager');

        $this->assertTrue($manager->can('enableBookings', $space));
    }

    public function test_admin_can_disable_any_lab(): void
    {
        $space = LabSpace::factory()->create();
        $admin = $this->createStaff('admin');

        $this->assertTrue($admin->can('disableBookings', $space));
    }

    public function test_admin_can_enable_any_lab(): void
    {
        $space = LabSpace::factory()->create();
        $admin = $this->createStaff('admin');

        $this->assertTrue($admin->can('enableBookings', $space));
    }

    public function test_superadmin_can_disable_any_lab(): void
    {
        $space = LabSpace::factory()->create();
        $superadmin = $this->createStaff('super_admin');

        $this->assertTrue($superadmin->can('disableBookings', $space));
    }

    public function test_superadmin_can_enable_any_lab(): void
    {
        $space = LabSpace::factory()->create();
        $superadmin = $this->createStaff('super_admin');

        $this->assertTrue($superadmin->can('enableBookings', $space));
    }

    public function test_lab_supervisor_cannot_disable_lab(): void
    {
        $space = LabSpace::factory()->create();
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space->id);

        $this->assertFalse($supervisor->can('disableBookings', $space));
    }

    public function test_lab_supervisor_cannot_enable_lab(): void
    {
        $space = LabSpace::factory()->create();
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space->id);

        $this->assertFalse($supervisor->can('enableBookings', $space));
    }

    public function test_member_cannot_disable_lab(): void
    {
        $space = LabSpace::factory()->create();
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->assertFalse($member->can('disableBookings', $space));
    }

    public function test_member_cannot_enable_lab(): void
    {
        $space = LabSpace::factory()->create();
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->assertFalse($member->can('enableBookings', $space));
    }

    // ==================== Lab Booking Policy Tests ====================

    public function test_lab_manager_can_cancel_any_booking(): void
    {
        $booking = LabBooking::factory()->create();
        $manager = $this->createStaff('lab_manager');

        $this->assertTrue($manager->can('cancelBooking', $booking));
    }

    public function test_admin_can_cancel_any_booking(): void
    {
        $booking = LabBooking::factory()->create();
        $admin = $this->createStaff('admin');

        $this->assertTrue($admin->can('cancelBooking', $booking));
    }

    public function test_superadmin_can_cancel_any_booking(): void
    {
        $booking = LabBooking::factory()->create();
        $superadmin = $this->createStaff('super_admin');

        $this->assertTrue($superadmin->can('cancelBooking', $booking));
    }

    public function test_lab_supervisor_can_cancel_assigned_booking(): void
    {
        $space = LabSpace::factory()->create();
        $booking = LabBooking::factory()->create(['lab_space_id' => $space->id]);
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space->id);

        $this->assertTrue($supervisor->can('cancelBooking', $booking));
    }

    public function test_lab_supervisor_cannot_cancel_unassigned_booking(): void
    {
        $space1 = LabSpace::factory()->create();
        $space2 = LabSpace::factory()->create();
        $booking = LabBooking::factory()->create(['lab_space_id' => $space2->id]);
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space1->id);

        $this->assertFalse($supervisor->can('cancelBooking', $booking));
    }

    public function test_member_cannot_cancel_booking(): void
    {
        $booking = LabBooking::factory()->create();
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->assertFalse($member->can('cancelBooking', $booking));
    }

    public function test_lab_manager_can_initiate_refund(): void
    {
        $booking = LabBooking::factory()->create();
        $manager = $this->createStaff('lab_manager');

        $this->assertTrue($manager->can('initiateRefund', $booking));
    }

    public function test_admin_can_initiate_refund(): void
    {
        $booking = LabBooking::factory()->create();
        $admin = $this->createStaff('admin');

        $this->assertTrue($admin->can('initiateRefund', $booking));
    }

    public function test_superadmin_can_initiate_refund(): void
    {
        $booking = LabBooking::factory()->create();
        $superadmin = $this->createStaff('super_admin');

        $this->assertTrue($superadmin->can('initiateRefund', $booking));
    }

    public function test_lab_supervisor_can_initiate_refund_for_assigned_booking(): void
    {
        $space = LabSpace::factory()->create();
        $booking = LabBooking::factory()->create(['lab_space_id' => $space->id]);
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space->id);

        $this->assertTrue($supervisor->can('initiateRefund', $booking));
    }

    public function test_lab_supervisor_cannot_initiate_refund_for_unassigned_booking(): void
    {
        $space1 = LabSpace::factory()->create();
        $space2 = LabSpace::factory()->create();
        $booking = LabBooking::factory()->create(['lab_space_id' => $space2->id]);
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space1->id);

        $this->assertFalse($supervisor->can('initiateRefund', $booking));
    }

    public function test_member_cannot_initiate_refund(): void
    {
        $booking = LabBooking::factory()->create();
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->assertFalse($member->can('initiateRefund', $booking));
    }

    // ==================== Scope Tests ====================

    public function test_lab_manager_can_access_all_labs(): void
    {
        $space1 = LabSpace::factory()->create();
        $space2 = LabSpace::factory()->create();
        $manager = $this->createStaff('lab_manager');

        $this->assertTrue($manager->can('disableBookings', $space1));
        $this->assertTrue($manager->can('disableBookings', $space2));
    }

    public function test_lab_supervisor_cannot_access_unassigned_labs(): void
    {
        $space1 = LabSpace::factory()->create();
        $space2 = LabSpace::factory()->create();
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space1->id);

        // Lab supervisors cannot disable/enable labs at all
        $this->assertFalse($supervisor->can('disableBookings', $space1));
        $this->assertFalse($supervisor->can('disableBookings', $space2));
    }
}
