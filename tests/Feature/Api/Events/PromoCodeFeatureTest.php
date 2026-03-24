<?php

namespace Tests\Feature\Api\Events;

use App\Models\County;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Plan;
use App\Models\SystemFeature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PromoCodeFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected bool $shouldSeedRoles = true;

    protected User $admin;

    protected User $user;

    protected EventCategory $category;

    protected County $county;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create(['email' => 'admin@test.local']);
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo([
            'create_events',
            'edit_events',
            'view_all_events',
            'manage_event_attendees',
        ]);

        $this->user = User::factory()->create();

        $this->category = EventCategory::factory()->create();
        $this->county = County::factory()->create();
    }

    #[Test]
    public function test_admin_can_create_event_with_promo_codes(): void
    {
        $eventData = [
            'title' => 'Promo Event',
            'description' => 'Event with promo codes',
            'category_id' => $this->category->id,
            'county_id' => $this->county->id,
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'ends_at' => now()->addMonth()->addHours(2)->toDateTimeString(),
            'venue' => 'Main Hall',
            'is_online' => false,
            'capacity' => 100,
            'price' => 1000,
            'currency' => 'KES',
            'status' => 'draft',
            'tickets' => [
                [
                    'name' => 'Standard',
                    'price' => 1000,
                    'quantity' => 100,
                ],
            ],
            'promo_codes' => [
                [
                    'code' => 'SAVE50',
                    'discount_type' => 'percentage',
                    'discount_value' => 50,
                    'usage_limit' => 10,
                    'is_active' => true,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/events', $eventData);

        $response->assertCreated();
        $event = Event::where('title', 'Promo Event')->first();
        $this->assertCount(1, $event->promoCodes);
        $this->assertEquals('SAVE50', $event->promoCodes->first()->code);
    }

    #[Test]
    public function test_promo_code_validation_endpoint(): void
    {
        $event = Event::factory()->create([
            'category_id' => $this->category->id,
            'county_id' => $this->county->id,
        ]);
        $ticket = $event->tickets()->create([
            'name' => 'Early Bird',
            'price' => 1000,
            'quantity' => 50,
        ]);
        $event->promoCodes()->create([
            'code' => 'TICKETOFF',
            'discount_type' => 'fixed',
            'discount_value' => 200,
            'usage_limit' => 5,
            'ticket_id' => $ticket->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/events/{$event->id}/validate-promo?code=TICKETOFF&ticket_id={$ticket->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'TICKETOFF',
                    'discount_type' => 'fixed',
                    'discount_value' => 200,
                ],
            ]);
    }

    #[Test]
    public function test_promo_code_tier_mismatch_fails_validation(): void
    {
        $event = Event::factory()->create([
            'category_id' => $this->category->id,
            'county_id' => $this->county->id,
        ]);
        $ticket1 = $event->tickets()->create(['name' => 'T1', 'price' => 500, 'quantity' => 10]);
        $ticket2 = $event->tickets()->create(['name' => 'T2', 'price' => 1000, 'quantity' => 10]);

        $event->promoCodes()->create([
            'code' => 'T1ONLY',
            'discount_type' => 'fixed',
            'discount_value' => 100,
            'ticket_id' => $ticket1->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/events/{$event->id}/validate-promo?code=T1ONLY&ticket_id={$ticket2->id}");

        $response->assertUnprocessable()
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function test_promo_code_waitlist_restriction(): void
    {
        $event = Event::factory()->create([
            'category_id' => $this->category->id,
            'county_id' => $this->county->id,
            'waitlist_enabled' => true,
            'capacity' => 1,
            'price' => 1000,
            'status' => 'published',
        ]);
        $ticket = $event->tickets()->create([
            'name' => 'Sold Out Tier',
            'price' => 1000,
            'quantity' => 1,
            'available' => 1,
        ]);

        // Manually trigger "isFull" condition in logic
        // The service checks if confirmed + paid + quantity > capacity
        // We have 0 confirmed, 0 paid. Capacity is 1. quantity is 1. (0+0+1) > 1 -> false.
        // Wait, I need to make sure confirmed registrations exist.
        \App\Models\EventRegistration::create([
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'user_id' => User::factory()->create()->id,
            'status' => 'confirmed',
            'confirmation_code' => 'FULL',
        ]);

        $event->promoCodes()->create([
            'code' => 'CANTUSE',
            'discount_type' => 'percentage',
            'discount_value' => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/events/{$event->id}/purchase", [
                'ticket_id' => $ticket->id,
                'quantity' => 1,
                'promo_code' => 'CANTUSE',
            ]);

        // Should return success but as waitlisted, and total amount should be ORIGINAL (if we allow purchase on waitlist? Usually we do but without discount)
        // Actually, the current logic calculates discount ONLY if !isFull.
        $response->assertOk();
        $order = \App\Models\EventOrder::latest()->first();
        $this->assertEquals('waitlisted', $order->status);
        $this->assertEquals(1000, $order->total_amount);
        $this->assertNull($order->promo_code_id);
    }

    #[Test]
    public function test_promo_code_usage_limit_enforced(): void
    {
        $event = Event::factory()->create([
            'category_id' => $this->category->id,
            'county_id' => $this->county->id,
        ]);
        $ticket = $event->tickets()->create(['name' => 'T', 'price' => 500, 'quantity' => 20]);

        $event->promoCodes()->create([
            'code' => 'LIMIT1',
            'discount_type' => 'fixed',
            'discount_value' => 100,
            'usage_limit' => 1,
            'used_count' => 1,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/events/{$event->id}/validate-promo?code=LIMIT1&ticket_id={$ticket->id}");

        $response->assertUnprocessable()
            ->assertJsonFragment(['message' => 'This promo code has reached its usage limit.']);
    }

    #[Test]
    public function test_promo_code_stacking_with_premium_discount(): void
    {
        // Setup premium plan with 20% discount
        $plan = Plan::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'is_active' => true,
            'price' => 1000,
            'signup_fee' => 0,
            'currency' => 'KES',
            'invoice_period' => 1,
            'invoice_interval' => 'month',
        ]);

        $feature = SystemFeature::create([
            'name' => 'Ticket Discount',
            'slug' => 'ticket_discount_percent',
            'value_type' => 'number',
        ]);

        $plan->systemFeatures()->attach($feature->id, ['value' => '20']);

        \App\Models\PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => 'user',
            'plan_id' => $plan->id,
            'slug' => 'main',
            'name' => 'main',
            'starts_at' => now(),
            'status' => 'active',
        ]);

        // Update user subscription status for hasActiveSubscription() check
        $this->user->update([
            'subscription_status' => 'active',
            'subscription_expires_at' => null, // Null means never expires
        ]);

        $event = Event::factory()->create([
            'category_id' => $this->category->id,
            'county_id' => $this->county->id,
            'is_online' => false,
            'venue' => 'Main',
            'price' => 1000,
        ]);
        $ticket = $event->tickets()->create(['name' => 'VIP', 'price' => 1000, 'quantity' => 10, 'available' => 10]);

        $event->promoCodes()->create([
            'code' => 'PROMO50',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/events/{$event->id}/purchase", [
                'ticket_id' => $ticket->id,
                'quantity' => 1,
                'promo_code' => 'PROMO50',
            ]);

        $response->assertOk();
        $order = \App\Models\EventOrder::latest()->first();

        // Base: 1000
        // Promo 50%: -500
        // Subtotal: 500
        // Subscriber 20% of subtotal: -100
        // Total: 400

        $this->assertEquals(400, $order->total_amount);
        $this->assertEquals(500, $order->promo_discount_amount);
        $this->assertEquals(100, $order->subscriber_discount_amount);
    }
}
