<?php

namespace Tests\Unit\Models;

use App\Models\AttendanceLog;
use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use App\Models\LabSpace;
use App\Models\MaintenanceBlockRollover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LabManagementModelsTest extends TestCase
{
    use RefreshDatabase;

    // ==================== LabSpace Model Tests ====================

    public function test_lab_space_has_bookings_enabled_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('lab_spaces', 'bookings_enabled'),
            'lab_spaces table missing bookings_enabled column'
        );
    }

    public function test_lab_space_has_member_capacity_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('lab_spaces', 'member_capacity'),
            'lab_spaces table missing member_capacity column'
        );
    }

    public function test_lab_space_factory_creates_valid_space(): void
    {
        $space = LabSpace::factory()->create();
        $this->assertNotNull($space->id);
        $this->assertNotNull($space->slug);
        $this->assertNotNull($space->name);
    }

    public function test_lab_space_can_toggle_bookings_enabled(): void
    {
        $space = LabSpace::factory()->create(['bookings_enabled' => true]);
        $this->assertTrue($space->bookings_enabled);

        $space->update(['bookings_enabled' => false]);
        $this->assertFalse($space->refresh()->bookings_enabled);
    }

    // ==================== LabBooking Model Tests ====================

    public function test_lab_booking_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('lab_bookings', 'lab_space_id'));
        $this->assertTrue(Schema::hasColumn('lab_bookings', 'user_id'));
        $this->assertTrue(Schema::hasColumn('lab_bookings', 'status'));
        $this->assertTrue(Schema::hasColumn('lab_bookings', 'starts_at'));
        $this->assertTrue(Schema::hasColumn('lab_bookings', 'ends_at'));
    }

    public function test_lab_booking_relationships(): void
    {
        $booking = LabBooking::factory()->create();
        $this->assertNotNull($booking->labSpace);
        $this->assertNotNull($booking->user);
    }

    public function test_lab_booking_can_have_confirmed_status(): void
    {
        $booking = LabBooking::factory()->create(['status' => 'confirmed']);
        $this->assertEquals('confirmed', $booking->status);
    }

    public function test_lab_booking_can_have_cancelled_status(): void
    {
        $booking = LabBooking::factory()->create(['status' => 'cancelled']);
        $this->assertEquals('cancelled', $booking->status);
    }

    // ==================== MaintenanceBlockRollover Model Tests ====================

    public function test_maintenance_block_rollovers_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('maintenance_block_rollovers'),
            'maintenance_block_rollovers table does not exist'
        );
    }

    public function test_maintenance_block_rollover_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('maintenance_block_rollovers', 'maintenance_block_id'));
        $this->assertTrue(Schema::hasColumn('maintenance_block_rollovers', 'original_booking_id'));
        $this->assertTrue(Schema::hasColumn('maintenance_block_rollovers', 'rolled_over_booking_id'));
        $this->assertTrue(Schema::hasColumn('maintenance_block_rollovers', 'status'));
    }

    public function test_maintenance_block_rollover_can_have_pending_status(): void
    {
        $block = LabMaintenanceBlock::factory()->create();
        $booking = LabBooking::factory()->create();

        $rollover = MaintenanceBlockRollover::create([
            'maintenance_block_id' => $block->id,
            'original_booking_id' => $booking->id,
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $rollover->status);
    }

    public function test_maintenance_block_rollover_can_have_null_rolled_over_booking(): void
    {
        $block = LabMaintenanceBlock::factory()->create();
        $booking = LabBooking::factory()->create();

        $rollover = MaintenanceBlockRollover::create([
            'maintenance_block_id' => $block->id,
            'original_booking_id' => $booking->id,
            'rolled_over_booking_id' => null,
            'status' => 'pending',
        ]);

        $this->assertNull($rollover->rolled_over_booking_id);
    }

    public function test_maintenance_block_rollover_relationships(): void
    {
        $block = LabMaintenanceBlock::factory()->create();
        $booking = LabBooking::factory()->create();

        $rollover = MaintenanceBlockRollover::create([
            'maintenance_block_id' => $block->id,
            'original_booking_id' => $booking->id,
            'status' => 'pending',
        ]);

        $this->assertEquals($block->id, $rollover->maintenance_block_id);
        $this->assertEquals($booking->id, $rollover->original_booking_id);
    }

    // ==================== AttendanceLog Model Tests ====================

    public function test_attendance_logs_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('attendance_logs'),
            'attendance_logs table does not exist'
        );
    }

    public function test_attendance_log_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('attendance_logs', 'booking_id'));
        $this->assertTrue(Schema::hasColumn('attendance_logs', 'lab_id'));
        $this->assertTrue(Schema::hasColumn('attendance_logs', 'user_id'));
        $this->assertTrue(Schema::hasColumn('attendance_logs', 'status'));
        $this->assertTrue(Schema::hasColumn('attendance_logs', 'check_in_time'));
        $this->assertTrue(Schema::hasColumn('attendance_logs', 'marked_by_id'));
    }

    public function test_attendance_log_can_be_created(): void
    {
        $booking = LabBooking::factory()->create();
        $user = User::factory()->create();

        $log = AttendanceLog::create([
            'booking_id' => $booking->id,
            'lab_id' => $booking->lab_space_id,
            'user_id' => $user->id,
            'status' => 'attended',
            'check_in_time' => now(),
            'marked_by_id' => $user->id,
        ]);

        $this->assertNotNull($log->id);
        $this->assertEquals('attended', $log->status);
    }

    public function test_attendance_log_can_have_no_show_status(): void
    {
        $booking = LabBooking::factory()->create();

        $log = AttendanceLog::create([
            'booking_id' => $booking->id,
            'lab_id' => $booking->lab_space_id,
            'status' => 'no_show',
        ]);

        $this->assertEquals('no_show', $log->status);
    }

    public function test_attendance_log_can_have_pending_status(): void
    {
        $booking = LabBooking::factory()->create();

        $log = AttendanceLog::create([
            'booking_id' => $booking->id,
            'lab_id' => $booking->lab_space_id,
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $log->status);
    }

    public function test_attendance_log_user_id_is_nullable(): void
    {
        $booking = LabBooking::factory()->create();

        $log = AttendanceLog::create([
            'booking_id' => $booking->id,
            'lab_id' => $booking->lab_space_id,
            'user_id' => null,
            'status' => 'attended',
        ]);

        $this->assertNull($log->user_id);
        $this->assertNotNull($log->id);
    }

    public function test_attendance_log_marked_by_id_is_nullable(): void
    {
        $booking = LabBooking::factory()->create();

        $log = AttendanceLog::create([
            'booking_id' => $booking->id,
            'lab_id' => $booking->lab_space_id,
            'status' => 'pending',
            'marked_by_id' => null,
        ]);

        $this->assertNull($log->marked_by_id);
    }

    public function test_attendance_log_check_in_time_is_nullable(): void
    {
        $booking = LabBooking::factory()->create();

        $log = AttendanceLog::create([
            'booking_id' => $booking->id,
            'lab_id' => $booking->lab_space_id,
            'status' => 'pending',
            'check_in_time' => null,
        ]);

        $this->assertNull($log->check_in_time);
    }
}
