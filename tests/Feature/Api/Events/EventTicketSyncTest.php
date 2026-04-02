<?php

namespace Tests\Feature\Api\Events;

use App\Models\County;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Plan;
use App\Models\SystemFeature;
use App\Models\PlanSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventTicketSyncTest extends TestCase
{
    use RefreshDatabase;

    protected bool $shouldSeedRoles = true;
    protected User $admin;
    protected EventCategory $category;
    protected County $county;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo(['create_events', 'edit_events']);

        $this->category = EventCategory::factory()->create();
        $this->county = County::factory()->create();
    }

    #[Test]
    public function it_deactivates_paid_tickets_when_event_becomes_free(): void
    {
        // 1. Create a paid event with paid tickets
        $event = Event::factory()->create([
            'price' => 1000,
            'category_id' => $this->category->id,
            'county_id' => $this->county->id,
            'created_by' => $this->admin->id,
        ]);

        $paidTicket = Ticket::create([
            'event_id' => $event->id,
            'name' => 'Paid Entry',
            'price' => 1000,
            'quantity' => 100,
            'is_active' => true,
        ]);

        // 2. Update event to be free (price = 0)
        $updateData = [
            'price' => 0,
            'title' => 'Now Free Event',
        ];

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/events/{$event->id}", $updateData);

        $response->assertOk();

        // 3. Verify paid ticket is deactivated
        $this->assertDatabaseHas('tickets', [
            'id' => $paidTicket->id,
            'is_active' => false,
        ]);

        // 4. Verify a General Admission free ticket was created
        $this->assertDatabaseHas('tickets', [
            'event_id' => $event->id,
            'name' => 'General Admission',
            'price' => 0,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_keeps_existing_free_tickets_active_when_event_becomes_free(): void
    {
        $event = Event::factory()->create([
            'price' => 1000,
            'category_id' => $this->category->id,
            'county_id' => $this->county->id,
            'created_by' => $this->admin->id,
        ]);

        $freeTicket = Ticket::create([
            'event_id' => $event->id,
            'name' => 'Early Free Pass',
            'price' => 0,
            'quantity' => 50,
            'is_active' => true,
        ]);

        $updateData = ['price' => 0];

        $this->actingAs($this->admin)
            ->putJson("/api/admin/events/{$event->id}", $updateData)
            ->assertOk();

        $this->assertDatabaseHas('tickets', [
            'id' => $freeTicket->id,
            'is_active' => true,
        ]);
    }
}
