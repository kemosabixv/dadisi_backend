<?php

namespace Database\Factories;

use App\Models\SubscriptionEnhancement;
use App\Models\PlanSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionEnhancementFactory extends Factory
{
    protected $model = SubscriptionEnhancement::class;

    public function definition(): array
    {
        return [
                'subscription_id' => PlanSubscription::factory(),
                'status' => $this->faker->randomElement(['active', 'payment_pending', 'payment_failed', 'grace_period', 'suspended', 'cancelled']),
            'payment_failure_state' => null,
            'renewal_attempts' => 0,
            'max_renewal_attempts' => 3,
            'last_renewal_attempt_at' => null,
            'grace_period_started_at' => null,
            'grace_period_ends_at' => null,
            'next_retry_at' => null,
            'payment_method' => $this->faker->randomElement(['mobile_money', 'card']),
            'failure_reason' => null,
            'metadata' => [],
        ];
    }
}
