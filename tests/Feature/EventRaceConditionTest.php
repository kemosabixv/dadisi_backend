<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_recognizes_race_condition_when_capacity_is_filled()
    {
        // Create an event with 1 spot left
        $event = Event::factory()->published()->create([
            'capacity' => 1,
            'price' => 1000,
            'waitlist_enabled' => true
        ]);
        $ticket = Ticket::factory()->create([
            'event_id' => $event->id,
            'price' => 1000,
            'available' => 1
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // 1. User 1 registers and takes the last spot
        $this->actingAs($user1);
        $response1 = $this->postJson("/api/events/{$event->id}/purchase", [
            'ticket_id' => $ticket->id,
            'quantity' => 1,
        ]);
        $response1->assertStatus(200);
        
        // Manually mark the order as paid to consume capacity
        $order = \App\Models\EventOrder::find($response1->json('data.order_id'));
        $order->update(['status' => 'paid']);
        $event->refresh();

        // 2. User 2 attempts to register, but sends is_waitlist_action => false
        $this->actingAs($user2);
        $response = $this->postJson("/api/events/{$event->id}/purchase", [
            'ticket_id' => $ticket->id,
            'quantity' => 1,
            'is_waitlist_action' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_waitlisted' => true,
                    'is_race_condition' => true,
                ]
            ]);
            
        $this->assertStringContainsString('Waitlist confirmed', $response->json('message'));
    }

    public function test_rsvp_recognizes_race_condition_when_capacity_is_filled()
    {
        // Create a free event with 1 spot left
        $event = Event::factory()->published()->create([
            'capacity' => 1,
            'waitlist_enabled' => true
        ]);
        $ticket = Ticket::factory()->create([
            'event_id' => $event->id,
            'price' => 0,
            'available' => 1
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // 1. User 1 takes the spot
        $this->actingAs($user1);
        $this->postJson("/api/events/{$event->id}/register", [
            'ticket_id' => $ticket->id,
        ])->assertStatus(201);

        // 2. User 2 attempts to RSVP with is_waitlist_action => false
        $this->actingAs($user2);
        $response = $this->postJson("/api/events/{$event->id}/register", [
            'ticket_id' => $ticket->id,
            'is_waitlist_action' => false,
        ]);
        // Assert response has race condition flag
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'is_waitlisted' => true,
                'is_race_condition' => true,
            ]);
    }

    public function test_returns_sold_out_flag_when_waitlist_is_disabled_and_race_condition_occurs()
    {
        // Create an event with 1 spot left and waitlist DISABLED
        $event = Event::factory()->published()->create([
            'capacity' => 1,
            'waitlist_enabled' => false
        ]);
        $ticket = Ticket::factory()->create([
            'event_id' => $event->id,
            'price' => 0,
            'available' => 1
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        // 1. User 1 takes the spot
        $this->actingAs($user1);
        $this->postJson("/api/events/{$event->id}/register", [
            'ticket_id' => $ticket->id,
        ])->assertStatus(201);

        // 2. User 2 attempts to register
        $this->actingAs($user2);
        $response = $this->postJson("/api/events/{$event->id}/register", [
            'ticket_id' => $ticket->id,
            'is_waitlist_action' => false,
        ]);
        // Assert response indicates sold out
        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'is_sold_out' => true,
                'type' => 'capacity_exceeded',
            ]);
    }
}
