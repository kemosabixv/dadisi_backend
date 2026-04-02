<?php

namespace Tests\Feature\Api\Events;

use App\Models\County;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Plan;
use App\Models\SystemFeature;
use App\Models\User;
use App\Models\PromoCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventOrderDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected bool $shouldSeedRoles = true;

    protected User $user;
    protected Event $event;
    protected $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        // Create standard user
        $this->user = User::factory()->create();

        // Setup Event environment
        $category = EventCategory::factory()->create();
        $county = County::factory()->create();

        $this->event = Event::factory()->create([
            'category_id' => $category->id,
            'county_id' => $county->id,
            'price' => 1000,
            'currency' => 'KES',
            'capacity' => 100,
        ]);

        $this->ticket = $this->event->tickets()->create([
            'name' => 'Standard Ticket',
            'price' => 1000,
            'quantity' => 100,
        ]);
    }

    #[Test]
    public function test_standard_ticket_purchase_without_discounts(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/events/{$this->event->id}/purchase", [
                'ticket_id' => $this->ticket->id,
                'quantity' => 1,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.total_amount', '1000.00');
        $response->assertJsonPath('data.original_amount', '1000.00');
    }

    #[Test]
    public function test_promo_code_percentage_discount(): void
    {
        PromoCode::factory()->create([
            'event_id' => $this->event->id,
            'code' => 'SAVE20',
            'discount_type' => 'percentage',
            'discount_value' => 20, // 20%
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/events/{$this->event->id}/purchase", [
                'ticket_id' => $this->ticket->id,
                'quantity' => 1,
                'promo_code' => 'SAVE20',
            ]);

        $response->assertOk();
        // 1000 - 20% (200) = 800
        $response->assertJsonPath('data.total_amount', '800.00');
        $response->assertJsonPath('data.promo_discount', '200.00');
    }

    #[Test]
    public function test_subscriber_compounded_discount_with_promo(): void
    {
        // 1. Setup Premium Plan with 10% Subscriber Discount
        $plan = Plan::factory()->create(['name' => 'Premium']);
        
        // Add ticket_discount_percent feature with correct schema
        $feature = SystemFeature::where('slug', 'ticket_discount_percent')->first();
        if (!$feature) {
             $feature = SystemFeature::create([
                'name' => 'Ticket Discount',
                'slug' => 'ticket_discount_percent',
                'value_type' => 'number', // Not 'type'
                'is_active' => true,
            ]);
        }
        
        $plan->systemFeatures()->attach($feature->id, ['value' => '10']); // 10% subscriber discount

        // Update user to be an active subscriber
        $this->user->update([
            'subscription_status' => 'active',
            'subscription_activated_at' => now()->subDay(),
        ]);
        
        // Create the actual subscription record
        $this->user->subscriptions()->create([
            'plan_id' => $plan->id,
            'slug' => 'premium',
            'name' => 'Premium',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
            'subscriber_id' => $this->user->id,
            'subscriber_type' => 'user',
        ]);

        // 2. Setup Promo Code 20%
        PromoCode::factory()->create([
            'event_id' => $this->event->id,
            'code' => 'SAVE20',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/events/{$this->event->id}/purchase", [
                'ticket_id' => $this->ticket->id,
                'quantity' => 1,
                'promo_code' => 'SAVE20',
            ]);

        $response->assertOk();
        
        /*
         * Calculation Logic Verification:
         * Original: 1000
         * Promo Discount (20% of 1000): 200
         * Remaining: 800
         * Subscriber Discount (10% of 800): 80
         * Total: 720
         */
        $response->assertJsonPath('data.original_amount', '1000.00');
        $response->assertJsonPath('data.promo_discount', '200.00');
        $response->assertJsonPath('data.subscriber_discount', '80.00');
        $response->assertJsonPath('data.total_amount', '720.00');
    }

    #[Test]
    public function test_promo_code_fixed_amount_discount(): void
    {
        PromoCode::factory()->create([
            'event_id' => $this->event->id,
            'code' => 'SAVE150',
            'discount_type' => 'fixed',
            'discount_value' => 150,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/events/{$this->event->id}/purchase", [
                'ticket_id' => $this->ticket->id,
                'quantity' => 1,
                'promo_code' => 'SAVE150',
            ]);

        $response->assertOk();
        // 1000 - 150 = 850
        $response->assertJsonPath('data.total_amount', '850.00');
        $response->assertJsonPath('data.promo_discount', '150.00');
    }
}
