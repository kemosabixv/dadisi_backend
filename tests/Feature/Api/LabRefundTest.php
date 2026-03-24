<?php

namespace Tests\Feature\Api;

use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\Payment;
use App\Models\User;
use App\Models\QuotaCommitment;
use App\Models\Refund;
use App\Notifications\LabBookingQuotaRestored;
use App\Notifications\LabBookingRefundApproved;
use App\Notifications\LabBookingRefundProcessed;
use App\Notifications\LabBookingRefundRequested;
use App\Notifications\LabBookingRefundSubmitted;
use App\Notifications\LabBookingRefundRejected;
use App\Services\Contracts\LabBookingServiceContract;
use App\Services\Contracts\RefundServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LabRefundTest extends TestCase
{
    use RefreshDatabase;

    protected LabBookingServiceContract $bookingService;
    protected RefundServiceContract $refundService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->bookingService = app(LabBookingServiceContract::class);
        $this->refundService = app(RefundServiceContract::class);

        // Ensure super_admin role exists for notification tests
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);
    }

    #[Test]
    public function test_mpesa_refund_is_strictly_full_or_none()
    {
        $user = User::factory()->create();
        $labSpace = LabSpace::factory()->create();
        
        // 1. Full Refund Case (No Attendance)
        $booking = LabBooking::factory()->create([
            'user_id' => $user->id,
            'lab_space_id' => $labSpace->id,
            'payment_method' => 'mpesa',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'total_price' => 2000,
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);
        
        Payment::factory()->create([
            'payable_type' => 'lab_booking',
            'payable_id' => $booking->id,
            'amount' => 2000,
            'status' => 'paid',
            'method' => 'mpesa',
            'gateway' => 'pesapal',
        ]);

        $booking->refresh();
        $preview = $this->refundService->getLabBookingRefundPreview($booking);
        $this->assertEquals(2000, (float)$preview['amount'], $preview['explanation'] ?? 'M-Pesa full');
        $this->assertTrue($preview['is_full_refund']);

        // 2. No Refund Case (Partial Attendance)
        $booking->update(['checked_in_at' => now()->subHour()]);
        
        $preview = $this->refundService->getLabBookingRefundPreview($booking);
        $this->assertEquals(0, (float)$preview['amount']);
        $this->assertFalse($preview['is_eligible']);
    }

    #[Test]
    public function test_card_refund_allows_partial_calculation()
    {
        $user = User::factory()->create();
        $labSpace = LabSpace::factory()->create();
        
        $booking = LabBooking::factory()->create([
            'user_id' => $user->id,
            'lab_space_id' => $labSpace->id,
            'payment_method' => 'card',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'total_price' => 2000,
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);
        
        Payment::factory()->create([
            'payable_type' => 'lab_booking',
            'payable_id' => $booking->id,
            'amount' => 2000,
            'status' => 'paid',
            'method' => 'card',
            'gateway' => 'pesapal',
        ]);

        $booking->refresh();
        $preview = $this->refundService->getLabBookingRefundPreview($booking);
        $this->assertEquals(2000, (float)$preview['amount'], 'Full refund since it is entirely in the future.');
    }

    #[Test]
    public function test_quota_restoration_respects_anniversary_reset()
    {
        Notification::fake();
        $user = User::factory()->create();
        $labSpace = LabSpace::factory()->create();
        
        $commitment = QuotaCommitment::create([
            'user_id' => $user->id,
            'month_date' => now()->startOfMonth()->format('Y-m-d'),
            'committed_hours' => 20,
            'used_hours' => 5,
        ]);
        
        $booking = LabBooking::factory()->create([
            'user_id' => $user->id,
            'lab_space_id' => $labSpace->id,
            'payment_method' => LabBooking::PAYMENT_METHOD_QUOTA,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'quota_consumed' => true,
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $this->bookingService->cancelBooking($booking, 'User cancelled');
        
        $commitment->refresh();
        $this->assertEquals(3.0, (float)$commitment->used_hours);
        Notification::assertSentTo($user, LabBookingQuotaRestored::class);
    }

    #[Test]
    public function test_cancel_booking_workflow_by_payment_type()
    {
        Notification::fake();
        $user = User::factory()->create();
        $labSpace = LabSpace::factory()->create();
        
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $booking = LabBooking::factory()->create([
            'user_id' => $user->id,
            'lab_space_id' => $labSpace->id,
            'payment_method' => LabBooking::PAYMENT_METHOD_DIRECT,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);
        
        Payment::factory()->create([
            'payable_type' => 'lab_booking',
            'payable_id' => $booking->id,
            'amount' => 1000,
            'status' => 'paid',
            'method' => 'card',
            'gateway' => 'pesapal',
        ]);

        $booking->refresh();
        $this->bookingService->cancelBooking($booking, 'Change of plans');
        
        $this->assertDatabaseHas('refunds', [
            'refundable_type' => 'lab_booking',
            'refundable_id' => $booking->id,
            'status' => Refund::STATUS_PENDING,
        ]);
        
        Notification::assertSentTo($user, LabBookingRefundSubmitted::class);
        Notification::assertSentTo($admin, LabBookingRefundRequested::class);
        Notification::assertSentTo($user, \App\Notifications\LabBookingCancelled::class);
    }
}
