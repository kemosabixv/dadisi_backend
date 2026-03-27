<?php

namespace Tests\Feature\Admin;

use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LabOperationTest extends TestCase
{
    use RefreshDatabase;

    private User $labManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->labManager = User::factory()->create();
        $this->labManager->assignRole('lab_manager');
    }

    public function test_staff_can_mark_attendance(): void
    {
        $booking = LabBooking::factory()->create([
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $this->actingAs($this->labManager);
        $response = $this->postJson("/api/admin/lab-bookings/{$booking->id}/check-in", [
            'is_present' => true,
        ]);

        $response->assertStatus(200);
        $this->assertTrue((bool)$booking->fresh()->is_present);
        $this->assertNotNull($booking->fresh()->checked_in_at);
        $this->assertEquals($this->labManager->id, $booking->fresh()->checked_in_by);
    }

    public function test_non_staff_cannot_mark_attendance(): void
    {
        $user = User::factory()->create();
        $booking = LabBooking::factory()->create([
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $this->actingAs($user);
        $response = $this->postJson("/api/admin/lab-bookings/{$booking->id}/check-in", [
            'is_present' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_staff_can_view_all_lab_bookings(): void
    {
        LabBooking::factory()->count(3)->create();

        $this->actingAs($this->labManager);
        $response = $this->getJson('/api/admin/lab-bookings');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }
}
