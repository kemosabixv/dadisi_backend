<?php

namespace Database\Factories;

use App\Models\PlanSubscription;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanSubscriptionFactory extends Factory
{
    protected $model = PlanSubscription::class;

    public function definition()
    {
        return [
            'subscriber_id' => User::factory(),
            'subscriber_type' => User::class,
            'plan_id' => Plan::factory(),
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'slug' => $this->faker->unique()->slug(),
            'status' => 'active',
        ];
    }

    public function forUser(User $user)
    {
        return $this->state(fn (array $attributes) => [
            'subscriber_id' => $user->id,
            'subscriber_type' => User::class,
        ]);
    }

    public function withPlan(Plan $plan)
    {
        return $this->state(fn (array $attributes) => [
            'plan_id' => $plan->id,
        ]);
    }

    public function active()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    public function expired()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function cancelled()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function recurring()
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => true,
        ]);
    }

    public function recurring_disabled()
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => false,
        ]);
    }
}
