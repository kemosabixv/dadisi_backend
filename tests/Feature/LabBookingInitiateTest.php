<?php

namespace Tests\Feature;

use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\User;
use App\Models\BookingSeries;
use App\Models\SlotHold;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Services\LabBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LabBookingInitiateTest extends TestCase
{
    use RefreshDatabase;

    protected bool $shouldSeedRoles = true;
    protected LabBookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LabBookingService::class);
    }

    #[Test]
    public function initiate_booking_uses_efficient_query_count()
    {
        // 1. Setup
        $user = User::factory()->create();
        $lab = LabSpace::factory()->create();
        
        // Create 20 slots for a recurring series
        $slots = [];
        $start = Carbon::now()->next('Monday')->setHour(10)->setMinute(0);
        for ($i = 0; $i < 20; $i++) {
            $slots[] = [
                'starts_at' => $start->copy()->addWeeks($i)->toDateTimeString(),
                'ends_at' => $start->copy()->addWeeks($i)->addHours(2)->toDateTimeString(),
            ];
        }

        // 2. Enable Query Log
        DB::enableQueryLog();
        DB::flushQueryLog();

        // 3. Action
        $result = $this->service->initiateBooking($user, [
            'lab_space_id' => $lab->id,
            'slots' => $slots,
            'type' => 'recurring',
        ]);

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // 4. Assertions
        $this->assertTrue($result['success']);
        // Pre-fetching should ensure O(1) queries for bookings, holds, and blocks
        // Total queries should be low (Transaction, Space lookup, Pre-fetch, Series Save, Holds Bulk Save, Quota check)
        $this->assertLessThan(60, $queryCount, "Initiate booking query count ($queryCount) is too high. Pre-fetching may not be working.");
    }

    #[Test]
    public function initiate_guest_booking_saves_metadata()
    {
        // 1. Setup
        $lab = LabSpace::factory()->create();
        $guestData = [
            'lab_space_id' => $lab->id,
            'slots' => [
                [
                    'starts_at' => Carbon::now()->addDay()->setHour(10)->toDateTimeString(),
                    'ends_at' => Carbon::now()->addDay()->setHour(12)->toDateTimeString(),
                ]
            ],
            'guest_name' => 'John Guest',
            'guest_email' => 'guest@example.com',
            'purpose' => 'Testing Guest Flow',
            'title' => 'Guest Reservation'
        ];

        // 2. Action
        $result = $this->service->initiateBooking(null, $guestData);

        // 3. Assertions
        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('booking_series', [
            'id' => $result['series_id'],
            'status' => 'pending',
        ]);

        $series = BookingSeries::find($result['series_id']);
        $this->assertEquals('John Guest', $series->metadata['guest_name'] ?? null);
        $this->assertEquals('guest@example.com', $series->metadata['guest_email'] ?? null);
        $this->assertEquals('Testing Guest Flow', $series->metadata['purpose'] ?? null);

        $hold = SlotHold::where('series_id', $series->id)->first();
        $this->assertEquals('guest@example.com', $hold->guest_email);
        $this->assertNull($hold->user_id);
    }

    #[Test]
    public function guest_booking_lifecycle_hold_to_confirm()
    {
        // 1. Initiate Hold
        $lab = LabSpace::factory()->create(['hourly_rate' => 500]);
        $guestData = [
            'lab_space_id' => $lab->id,
            'slots' => [
                [
                    'starts_at' => Carbon::now()->addDay()->setHour(14)->toDateTimeString(),
                    'ends_at' => Carbon::now()->addDay()->setHour(16)->toDateTimeString(),
                ]
            ],
            'guest_name' => 'Quick Guest',
            'guest_email' => 'quick@example.com',
        ];

        $initResult = $this->service->initiateBooking(null, $guestData);
        $reference = $initResult['reference'];

        // 2. Confirm Guest
        $confirmData = [
            'reference' => $reference,
            'payment_method' => 'mpesa',
            'payment_reference' => 'TXN123456',
            'guest_data' => [
                'name' => 'Quick Guest',
                'email' => 'quick@example.com',
                'phone' => '0712345678'
            ]
        ];

        $confirmResult = $this->service->confirmGuest(
            $confirmData['reference'],
            $confirmData['payment_reference'],
            $confirmData['payment_method'],
            $confirmData['guest_data']
        );

        // 3. Assertions
        $this->assertTrue($confirmResult['success']);
        
        $this->assertDatabaseHas('lab_bookings', [
            'booking_series_id' => $initResult['series_id'],
            'guest_email' => 'quick@example.com',
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $this->assertDatabaseHas('payments', [
            'transaction_id' => 'TXN123456',
            'external_reference' => 'TXN123456',
            'status' => 'paid',
            'amount' => 1000, // 2 hours * 500
        ]);

        // Holds should be deleted
        $this->assertDatabaseMissing('slot_holds', ['reference' => $reference]);
        
        // Series should be active
        $this->assertDatabaseHas('booking_series', [
            'id' => $initResult['series_id'],
            'status' => 'confirmed',
        ]);
    }
}
