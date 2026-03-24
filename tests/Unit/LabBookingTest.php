<?php

namespace Tests\Unit;

use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\User;
use App\Services\LabManagement\LabBookingCancellationService;
use App\Services\SystemSettingService;
use App\Services\Contracts\RefundServiceContract;
use App\Exceptions\LabException;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

use PHPUnit\Framework\Attributes\Test;

class LabBookingTest extends TestCase
{
    use RefreshDatabase;

    protected $cancellationService;
    protected $settingsMock;
    protected $refundServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        
        $this->settingsMock = Mockery::mock(SystemSettingService::class);
        $this->refundServiceMock = Mockery::mock(RefundServiceContract::class);
        
        $this->cancellationService = new LabBookingCancellationService(
            $this->refundServiceMock,
            $this->settingsMock
        );
    }

    #[Test]
    public function user_can_cancel_booking_before_deadline()
    {
        // 1. Setup Data
        $user = User::factory()->create();
        
        // Booking is 2 days from now
        $booking = LabBooking::factory()->create([
            'user_id' => $user->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $this->settingsMock->shouldReceive('get')
            ->with('lab_booking_cancellation_deadline_days', 1)
            ->andReturn(1);

        // 2. Action
        $result = $this->cancellationService->cancelBooking($booking, $user);

        // 3. Assertions
        $this->assertTrue($result);
        $this->assertEquals(LabBooking::STATUS_CANCELLED, $booking->status);
    }

    #[Test]
    public function user_cannot_cancel_booking_after_deadline()
    {
        // 1. Setup Data
        $user = User::factory()->create();
        
        // Booking is in 12 hours (deadline is 24 hours/1 day)
        $booking = LabBooking::factory()->create([
            'user_id' => $user->id,
            'starts_at' => now()->addHours(12),
            'ends_at' => now()->addHours(14),
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $this->settingsMock->shouldReceive('get')
            ->with('lab_booking_cancellation_deadline_days', 1)
            ->andReturn(1);

        // 2. Action & Assertion
        $this->expectException(LabException::class);
        $this->expectExceptionMessage("Cancellations are only allowed up to 1 day(s) before the booking starts.");
        
        $this->cancellationService->cancelBooking($booking, $user);
    }

    #[Test]
    public function staff_can_override_cancellation_deadline()
    {
        // 1. Setup Data
        $user = User::factory()->create(); 
        $staff = User::factory()->create();
        
        // Assign super_admin role for AdminAccessResolver
        $staff->assignRole('super_admin');
        
        // Booking is in 12 hours
        $booking = LabBooking::factory()->create([
            'user_id' => $user->id,
            'starts_at' => now()->addHours(12),
            'ends_at' => now()->addHours(14),
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        // 2. Action
        $result = $this->cancellationService->cancelBooking($booking, $staff, "Staff override");

        // 3. Assertions
        $this->assertTrue($result);
        $this->assertEquals(LabBooking::STATUS_CANCELLED, $booking->status);
    }

    #[Test]
    public function quota_is_restored_on_cancellation()
    {
        // 1. Setup Data
        $user = User::factory()->create();
        
        $booking = LabBooking::factory()->create([
            'user_id' => $user->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'status' => LabBooking::STATUS_CONFIRMED,
            'quota_consumed' => true,
        ]);

        $this->settingsMock->shouldReceive('get')
            ->with('lab_booking_cancellation_deadline_days', 1)
            ->andReturn(1);

        // 2. Action
        $this->cancellationService->cancelBooking($booking, $user);

        // 3. Assertions
        $this->assertFalse($booking->quota_consumed);
        $this->assertEquals(LabBooking::STATUS_CANCELLED, $booking->status);
    }

    #[Test]
    public function booking_fails_if_capacity_is_exceeded()
    {
        // 1. Setup Data
        $lab = LabSpace::factory()->create(['capacity' => 1]);
        $time = now()->addDays(1)->startOfHour()->setHour(10);
        
        // Existing booking at the same time
        LabBooking::factory()->create([
            'lab_space_id' => $lab->id,
            'starts_at' => $time,
            'ends_at' => $time->copy()->addHour(),
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        // 2. Action
        $bookingService = app(\App\Services\LabBookingService::class);
        $isAvailable = $bookingService->checkAvailability($lab->id, $time, $time->copy()->addHour());

        // 3. Assertion
        $this->assertFalse($isAvailable);
    }

    #[Test]
    public function book_lab_space_detects_race_condition()
    {
        // 1. Setup Data
        $user = User::factory()->create();
        $lab = LabSpace::factory()->create(['capacity' => 1]);
        $time = now()->addDays(1)->startOfHour()->setHour(10);
        
        // Use a partial mock of LabBookingService to simulate race condition
        $bookingService = Mockery::mock(\App\Services\LabBookingService::class)->makePartial();
        
        // Mock canBook to return success
        $bookingService->shouldReceive('canBook')
            ->once()
            ->andReturn(['allowed' => true]);

        // First check (outside transaction) returns TRUE (it was available)
        $bookingService->shouldReceive('checkAvailability')
            ->once()
            ->andReturn(true);
            
        // Second check (inside transaction) returns FALSE (someone took it)
        $bookingService->shouldReceive('checkAvailability')
            ->once()
            ->andReturn(false);

        $data = [
            'lab_space_id' => $lab->id,
            'starts_at' => $time->toDateTimeString(),
            'ends_at' => $time->copy()->addHour()->toDateTimeString(),
            'purpose' => 'Testing race condition',
        ];

        // 2. Action
        $result = $bookingService->createBooking($user, $data);

        // 3. Assertions
        $this->assertFalse($result['success']);
        $this->assertTrue($result['is_race_condition']);
        $this->assertEquals('While you were booking, the last available spot for this slot was taken.', $result['message']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
