<?php

namespace Tests\Unit\Services;

use App\Models\Plan;
use App\Models\SystemFeature;
use App\Models\User;
use App\Services\EventQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use PHPUnit\Framework\Attributes\Test;

class EventQuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $shouldSeedRoles = true;

    private EventQuotaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventQuotaService();
    }

    #[Test]
    public function staff_can_always_create_events(): void
    {
        // Create staff user
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->assertTrue($this->service->canCreateEvent($user));
    }

    #[Test]
    public function regular_user_cannot_create_events(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service->canCreateEvent($user));
    }

    #[Test]
    public function regular_user_with_plan_cannot_create_events(): void
    {
        $plan = Plan::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['plan_id' => $plan->id]);

        $this->assertFalse($this->service->canCreateEvent($user));
    }

    #[Test]
    public function subscriber_discount_returns_zero_for_non_subscriber(): void
    {
        $user = User::factory()->create();

        $discount = $this->service->getSubscriberDiscount($user);

        $this->assertEquals(0, $discount);
    }

    #[Test]
    public function subscriber_discount_returns_plan_value(): void
    {
        $feature = SystemFeature::create([
            'slug' => 'ticket_discount_percent',
            'name' => 'Ticket Discount',
            'value_type' => 'number',
            'default_value' => '0',
            'is_active' => true,
        ]);

        $plan = Plan::factory()->create(['is_active' => true]);
        $plan->systemFeatures()->attach($feature->id, ['value' => '15']);

        $user = User::factory()->create([
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
        ]);

        $discount = $this->service->getSubscriberDiscount($user);

        $this->assertEquals(15.0, $discount);
    }

    #[Test]
    public function priority_access_returns_false_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service->hasPriorityAccess($user));
    }

    #[Test]
    public function priority_access_returns_plan_value(): void
    {
        $feature = SystemFeature::create([
            'slug' => 'priority_event_access',
            'name' => 'Priority Access',
            'value_type' => 'boolean',
            'default_value' => 'false',
            'is_active' => true,
        ]);

        $plan = Plan::factory()->create(['is_active' => true]);
        $plan->systemFeatures()->attach($feature->id, ['value' => 'true']);

        $user = User::factory()->create(['plan_id' => $plan->id]);

        $this->assertTrue($this->service->hasPriorityAccess($user));
    }

    #[Test]
    public function get_remaining_creations_returns_null_for_staff(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $remaining = $this->service->getRemainingCreations($user);

        $this->assertNull($remaining);
    }

    #[Test]
    public function get_remaining_creations_returns_zero_for_regular_user(): void
    {
        $user = User::factory()->create();

        $remaining = $this->service->getRemainingCreations($user);

        $this->assertEquals(0, $remaining);
    }
}
