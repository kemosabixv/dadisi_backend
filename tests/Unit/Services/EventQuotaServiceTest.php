<?php

namespace Tests\Unit\Services;

use App\Models\Plan;
use App\Models\SystemFeature;
use App\Models\User;
use App\Services\EventQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

    public function test_staff_can_always_create_events(): void
    {
        // Create staff user
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->assertTrue($this->service->canCreateEvent($user));
    }

    public function test_user_without_plan_cannot_create_events(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service->canCreateEvent($user));
    }

    public function test_user_with_plan_and_creation_limit_can_create(): void
    {
        // Create system feature
        $feature = SystemFeature::create([
            'slug' => 'event_creation_limit',
            'name' => 'Event Creation Limit',
            'value_type' => 'number',
            'default_value' => '0',
            'is_active' => true,
        ]);

        // Create plan with feature
        $plan = Plan::factory()->create(['is_active' => true]);
        $plan->systemFeatures()->attach($feature->id, ['value' => '5']);

        // Create user with plan
        $user = User::factory()->create(['plan_id' => $plan->id]);

        $this->assertTrue($this->service->canCreateEvent($user));
    }

    public function test_user_with_zero_creation_limit_cannot_create(): void
    {
        $feature = SystemFeature::create([
            'slug' => 'event_creation_limit',
            'name' => 'Event Creation Limit',
            'value_type' => 'number',
            'default_value' => '0',
            'is_active' => true,
        ]);

        $plan = Plan::factory()->create(['is_active' => true]);
        $plan->systemFeatures()->attach($feature->id, ['value' => '0']);

        $user = User::factory()->create(['plan_id' => $plan->id]);

        $this->assertFalse($this->service->canCreateEvent($user));
    }

    public function test_unlimited_creation_limit_returns_true(): void
    {
        $feature = SystemFeature::create([
            'slug' => 'event_creation_limit',
            'name' => 'Event Creation Limit',
            'value_type' => 'number',
            'default_value' => '0',
            'is_active' => true,
        ]);

        $plan = Plan::factory()->create(['is_active' => true]);
        $plan->systemFeatures()->attach($feature->id, ['value' => '-1']);

        $user = User::factory()->create(['plan_id' => $plan->id]);

        $this->assertTrue($this->service->canCreateEvent($user));
    }

    public function test_subscriber_discount_returns_zero_for_non_subscriber(): void
    {
        $user = User::factory()->create();

        $discount = $this->service->getSubscriberDiscount($user);

        $this->assertEquals(0, $discount);
    }

    public function test_subscriber_discount_returns_plan_value(): void
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

    public function test_priority_access_returns_false_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service->hasPriorityAccess($user));
    }

    public function test_priority_access_returns_plan_value(): void
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

    public function test_get_remaining_creations_returns_null_for_staff(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $remaining = $this->service->getRemainingCreations($user);

        $this->assertNull($remaining);
    }

    public function test_get_remaining_creations_calculates_correctly(): void
    {
        $feature = SystemFeature::create([
            'slug' => 'event_creation_limit',
            'name' => 'Event Creation Limit',
            'value_type' => 'number',
            'default_value' => '0',
            'is_active' => true,
        ]);

        $plan = Plan::factory()->create(['is_active' => true]);
        $plan->systemFeatures()->attach($feature->id, ['value' => '5']);

        $user = User::factory()->create(['plan_id' => $plan->id]);

        $remaining = $this->service->getRemainingCreations($user);

        $this->assertEquals(5, $remaining);
    }
}
