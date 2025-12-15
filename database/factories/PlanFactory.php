<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => $this->faker->word()],
            'slug' => $this->faker->unique()->slug(),
            'description' => ['en' => $this->faker->sentence()],
            'is_active' => true,
            'price' => $this->faker->randomFloat(2, 0, 1000),
            'currency' => 'KES',
            'base_monthly_price' => $this->faker->randomFloat(2, 0, 1000),
            'yearly_discount_percent' => 20.00,
            'default_billing_period' => 1,
            'invoice_period' => 1,
            'invoice_interval' => 'month',
            'trial_period' => 0,
            'trial_interval' => 'day',
            'grace_period' => 0,
            'grace_interval' => 'day',
            'signup_fee' => 0.00,
            'sort_order' => 0,
        ];
    }
}
