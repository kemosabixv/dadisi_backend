<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceLog;
use App\Models\BookingSeries;
use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use App\Models\LabSpace;
use App\Models\SlotHold;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LabMappingConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // Need basic settings and roles
    }

    #[Test]
    public function lab_booking_resource_returns_standardized_fields()
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $labSpace = LabSpace::factory()->create();
        $booking = LabBooking::factory()->create([
            'user_id' => $user->id,
            'lab_space_id' => $labSpace->id,
            'purpose' => 'Research work',
            'admin_notes' => 'Some private notes',
            'booking_reference' => 'REF-123',
        ]);

        $response = $this->actingAs($user)->getJson("/api/bookings/{$booking->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.purpose', 'Research work')
            ->assertJsonPath('data.admin_notes', 'Some private notes')
            ->assertJsonPath('data.booking_reference', 'REF-123')
            ->assertJsonPath('data.reference', 'REF-123')
            ->assertJsonPath('data.payer_name', 'testuser');
    }

    #[Test]
    public function lab_maintenance_block_resource_includes_recurrence()
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $labSpace = LabSpace::factory()->create();
        
        $block = LabMaintenanceBlock::create([
            'lab_space_id' => $labSpace->id,
            'title' => 'Weekly Maintenance',
            'block_type' => 'maintenance',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'recurring' => true,
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO',
            'created_by' => $admin->id
        ]);

        $response = $this->actingAs($admin)->getJson("/api/admin/lab-maintenance?lab_space_id={$labSpace->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'recurring' => true,
                'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO'
            ])
            ->assertJsonMissing(['description', 'is_all_day']);
    }

    #[Test]
    public function booking_series_and_holds_are_accessible_via_resources()
    {
        $user = User::factory()->create();
        $labSpace = LabSpace::factory()->create();
        
        $series = BookingSeries::create([
            'user_id' => $user->id,
            'lab_space_id' => $labSpace->id,
            'reference' => 'SERIES-001',
            'type' => 'recurring',
            'status' => 'confirmed',
            'total_hours' => 10
        ]);

        $hold = SlotHold::create([
            'reference' => 'HOLD-001',
            'lab_space_id' => $labSpace->id,
            'user_id' => $user->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'expires_at' => now()->addMinutes(30),
            'series_id' => $series->id
        ]);

        // Note: These might not have dedicated index endpoints yet, 
        // but we test that the Resources can be instantiated and transform data.
        
        $resource = new \App\Http\Resources\BookingSeriesResource($series->load('holds'));
        $data = $resource->resolve();

        $this->assertEquals('SERIES-001', $data['reference']);
        $this->assertCount(1, $data['holds']);
        $this->assertEquals('HOLD-001', $data['holds'][0]['reference']);
    }
}
